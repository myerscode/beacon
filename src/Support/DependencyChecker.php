<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Support;

class DependencyChecker
{
    /**
     * Run all dependency checks.
     *
     * @return DependencyCheck[]
     */
    public function check(): array
    {
        $chrome       = $this->chrome();
        $chromeDriver = $this->chromeDriver();

        $checks = [
            $chrome,
            $chromeDriver,
            $this->versionCompatibility($chrome, $chromeDriver),
            $this->node(),
            $this->lighthouse(),
        ];

        return array_filter($checks);
    }

    /**
     * Check if Chrome or Chromium is installed.
     */
    public function chrome(): DependencyCheck
    {
        $candidates = $this->isWindows()
            ? $this->windowsChromePaths()
            : $this->unixChromePaths();

        foreach ($candidates as $candidate) {
            if ($this->isExecutable($candidate)) {
                $version = $this->getVersion($candidate);

                return new DependencyCheck(
                    'Chrome/Chromium',
                    true,
                    $candidate,
                    $version,
                    'Found at ' . $candidate . ($version !== null ? sprintf(' (v%s)', $version) : ''),
                );
            }
        }

        // Try finding via which/where
        $binary = $this->findBinary(['google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser', 'chrome']);

        if ($binary !== null) {
            $version = $this->getVersion($binary);

            return new DependencyCheck(
                'Chrome/Chromium',
                true,
                $binary,
                $version,
                'Found at ' . $binary . ($version !== null ? sprintf(' (v%s)', $version) : ''),
            );
        }

        return new DependencyCheck(
            'Chrome/Chromium',
            false,
            null,
            null,
            $this->isWindows()
                ? 'Not found. Install Google Chrome from https://www.google.com/chrome/'
                : 'Not found. Install via: brew install --cask google-chrome (macOS) or apt install chromium-browser (Linux)',
        );
    }

    /**
     * Check if ChromeDriver is installed.
     */
    public function chromeDriver(): DependencyCheck
    {
        $binary = $this->findBinary(['chromedriver'], ['./drivers']);

        if ($binary !== null) {
            $version = $this->getVersion($binary);

            return new DependencyCheck(
                'ChromeDriver',
                true,
                $binary,
                $version,
                'Found at ' . $binary . ($version !== null ? sprintf(' (v%s)', $version) : ''),
            );
        }

        return new DependencyCheck(
            'ChromeDriver',
            false,
            null,
            null,
            'Not found. Run "composer run driver:install" to install it.',
        );
    }

    /**
     * Check if Lighthouse CLI is installed.
     */
    public function lighthouse(): DependencyCheck
    {
        $binary = $this->findBinary(['lighthouse']);

        if ($binary !== null) {
            $version = $this->getVersion($binary);

            return new DependencyCheck(
                'Lighthouse',
                true,
                $binary,
                $version,
                'Found at ' . $binary . ($version !== null ? sprintf(' (v%s)', $version) : ''),
            );
        }

        return new DependencyCheck(
            'Lighthouse',
            false,
            null,
            null,
            'Not found. Run "composer run lighthouse:install" to install it.',
        );
    }

    /**
     * Check if Node.js is installed.
     */
    public function node(): DependencyCheck
    {
        $binary = $this->findBinary(['node']);

        if ($binary !== null) {
            $version = $this->getVersion($binary);

            return new DependencyCheck(
                'Node.js',
                true,
                $binary,
                $version,
                'Found at ' . $binary . ($version !== null ? sprintf(' (v%s)', $version) : ''),
            );
        }

        return new DependencyCheck(
            'Node.js',
            false,
            null,
            null,
            'Not found. Install from https://nodejs.org/ (required for Lighthouse features)',
        );
    }

    /**
     * Find a binary by name using which (Unix) or where (Windows).
     *
     * @param string[] $names
     * @param string[] $extraPaths
     */
    private function findBinary(array $names, array $extraPaths = []): ?string
    {
        // Check extra paths first (e.g. ./drivers)
        foreach ($extraPaths as $extraPath) {
            foreach ($names as $name) {
                $path = rtrim($extraPath, '/\\') . DIRECTORY_SEPARATOR . $name;

                if ($this->isExecutable($path)) {
                    return realpath($path) ?: $path;
                }
            }
        }

        $command = $this->isWindows() ? 'where' : 'which';

        foreach ($names as $name) {
            $output = [];
            $code   = 0;

            @exec(sprintf('%s %s 2>/dev/null', $command, $name), $output, $code);

            if ($code === 0 && isset($output[0]) && $output[0] !== '') {
                return trim($output[0]);
            }
        }

        return null;
    }

    /**
     * Get the version string from a binary.
     */
    private function getVersion(string $binary): ?string
    {
        $output = [];
        $code   = 0;

        @exec('"' . $binary . '" --version 2>/dev/null', $output, $code);
        if ($code !== 0) {
            return null;
        }
        if (!isset($output[0])) {
            return null;
        }
        // Extract version number from output like "Google Chrome 120.0.6099.109" or "ChromeDriver 120.0.6099.109"
        if (preg_match('/(\d+\.\d+[\.\d]*)/', $output[0], $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function isExecutable(string $path): bool
    {
        if ($this->isWindows()) {
            return file_exists($path);
        }

        return is_executable($path);
    }

    private function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /**
     * @return string[]
     */
    private function unixChromePaths(): array
    {
        $paths = [
            // macOS
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/Applications/Chromium.app/Contents/MacOS/Chromium',
            '/Applications/Google Chrome Canary.app/Contents/MacOS/Google Chrome Canary',
            // Linux common paths
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/local/bin/chromium',
            '/snap/bin/chromium',
        ];

        // Check CHROME_PATH env var
        $envPath = getenv('CHROME_PATH');

        if ($envPath !== false && $envPath !== '') {
            array_unshift($paths, $envPath);
        }

        return $paths;
    }

    /**
     * Check Chrome and ChromeDriver major versions match.
     */
    private function versionCompatibility(DependencyCheck $chrome, DependencyCheck $chromeDriver): ?DependencyCheck
    {
        if (!$chrome->found || !$chromeDriver->found || $chrome->version === null || $chromeDriver->version === null) {
            return null;
        }

        $chromeMajor = (int) explode('.', $chrome->version)[0];
        $driverMajor = (int) explode('.', $chromeDriver->version)[0];

        if ($chromeMajor === $driverMajor) {
            return new DependencyCheck(
                'Version Match',
                true,
                null,
                null,
                sprintf('Chrome (%d) and ChromeDriver (%d) major versions match', $chromeMajor, $driverMajor),
            );
        }

        return new DependencyCheck(
            'Version Match',
            false,
            null,
            null,
            sprintf('Chrome (%d) and ChromeDriver (%d) major versions differ. They must match.', $chromeMajor, $driverMajor),
        );
    }

    /**
     * @return string[]
     */
    private function windowsChromePaths(): array
    {
        $paths = [];

        // Check CHROME_PATH env var
        $envPath = getenv('CHROME_PATH');

        if ($envPath !== false && $envPath !== '') {
            $paths[] = $envPath;
        }

        $prefixes = array_filter([
            getenv('LOCALAPPDATA'),
            getenv('PROGRAMFILES'),
            getenv('PROGRAMFILES(X86)'),
        ]);

        $suffixes = [
            'Google\\Chrome\\Application\\chrome.exe',
            'Chromium\\Application\\chrome.exe',
            'Google\\Chrome SxS\\Application\\chrome.exe',
        ];

        foreach ($prefixes as $prefix) {
            foreach ($suffixes as $suffix) {
                $paths[] = $prefix . '\\' . $suffix;
            }
        }

        return $paths;
    }
}
