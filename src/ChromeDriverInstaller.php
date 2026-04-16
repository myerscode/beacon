<?php

declare(strict_types=1);

namespace Myerscode\Beacon;

use Myerscode\Beacon\Support\InstallationResult;
use RuntimeException;
use ZipArchive;

/**
 * Manages downloading and installing the correct ChromeDriver binary
 * for the currently installed version of Chrome/Chromium.
 */
class ChromeDriverInstaller
{
    public const DRIVERS_DIR = './drivers';

    private const CHROME_FOR_TESTING_VERSIONS_URL = 'https://googlechromelabs.github.io/chrome-for-testing/known-good-versions-with-downloads.json';

    private const CHROMEDRIVER_LATEST_URL = 'https://chromedriver.storage.googleapis.com/LATEST_RELEASE';

    /**
     * Remove the ChromeDriver binary from the drivers directory.
     */
    public function clean(?string $driversDir = null): InstallationResult
    {
        $driversDir = $this->resolveDriversDir($driversDir);
        $binary     = $driversDir . DIRECTORY_SEPARATOR . $this->binaryName();

        if (file_exists($binary)) {
            unlink($binary);

            return InstallationResult::removed(sprintf('ChromeDriver removed from %s', $binary));
        }

        return InstallationResult::nothing(sprintf('ChromeDriver not found in %s, nothing to clean.', $driversDir));
    }

    /**
     * Detect the installed Chrome version.
     */
    public function getChromeVersion(): ?string
    {
        $candidates = PHP_OS_FAMILY === 'Windows'
            ? $this->windowsChromePaths()
            : $this->unixChromePaths();

        foreach ($candidates as $candidate) {
            $version = $this->getBinaryVersion($candidate);

            if ($version !== null) {
                return $version;
            }
        }

        // Fallback: try which/where
        $names   = ['google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser', 'chrome'];
        $command = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';

        foreach ($names as $name) {
            $output = [];
            $code   = 0;
            @exec(sprintf('%s %s 2>/dev/null', $command, $name), $output, $code);

            if ($code === 0 && isset($output[0]) && $output[0] !== '') {
                $version = $this->getBinaryVersion(trim($output[0]));

                if ($version !== null) {
                    return $version;
                }
            }
        }

        return null;
    }

    /**
     * Install ChromeDriver matching the installed Chrome version.
     * Skips if already installed and version matches.
     */
    public function install(bool $force = false, ?string $driversDir = null): InstallationResult
    {
        $driversDir = $this->resolveDriversDir($driversDir);
        $binary     = $driversDir . DIRECTORY_SEPARATOR . $this->binaryName();

        if (!$force && file_exists($binary)) {
            $installedVersion = $this->getBinaryVersion($binary);
            $chromeVersion    = $this->getChromeVersion();

            if ($installedVersion !== null && $chromeVersion !== null) {
                $installedMajor = (int) explode('.', $installedVersion)[0];
                $chromeMajor    = (int) explode('.', $chromeVersion)[0];

                if ($installedMajor === $chromeMajor) {
                    return InstallationResult::skipped(
                        sprintf('ChromeDriver %s already installed and matches Chrome %s. Skipping.', $installedVersion, $chromeVersion),
                    );
                }
            }
        }

        return $this->download($driversDir);
    }

    /**
     * Force re-download of ChromeDriver, replacing any existing binary.
     */
    public function update(?string $driversDir = null): InstallationResult
    {
        return $this->install(force: true, driversDir: $driversDir);
    }

    protected function download(string $driversDir): InstallationResult
    {
        $chromeVersion = $this->getChromeVersion();

        if ($chromeVersion === null) {
            throw new RuntimeException(
                'Could not detect Chrome version. Ensure Chrome or Chromium is installed.',
            );
        }

        $chromeMajor = (int) explode('.', $chromeVersion)[0];
        $messages    = [sprintf('Detected Chrome version: %s (major: %d)', $chromeVersion, $chromeMajor)];

        [$downloadUrl, $resolveMessage] = $this->resolveDownloadUrl($chromeMajor, $chromeVersion);

        $messages[] = sprintf('Downloading ChromeDriver from: %s', $downloadUrl);

        if ($resolveMessage !== null) {
            $messages[] = $resolveMessage;
        }

        $zipContent = $this->fetchUrl($downloadUrl);
        $tmpZip     = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chromedriver_' . uniqid() . '.zip';

        file_put_contents($tmpZip, $zipContent);

        $this->extractBinary($tmpZip, $driversDir);

        @unlink($tmpZip);

        $binary = $driversDir . DIRECTORY_SEPARATOR . $this->binaryName();

        if (!file_exists($binary)) {
            throw new RuntimeException('ChromeDriver extraction failed — binary not found after unzip.');
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($binary, 0755);
        }

        $version = $this->getBinaryVersion($binary);

        return InstallationResult::success(
            sprintf('ChromeDriver %s installed to %s', $version ?? 'unknown', $binary),
            ...$messages,
        );
    }

    protected function getBinaryVersion(string $binary): ?string
    {
        if (!file_exists($binary) && !is_executable($binary)) {
            return null;
        }

        $output = [];
        $code   = 0;

        @exec('"' . $binary . '" --version 2>/dev/null', $output, $code);

        if ($code !== 0 || !isset($output[0])) {
            return null;
        }

        if (preg_match('/(\d+\.\d+[\.\d]*)/', $output[0], $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function binaryName(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'chromedriver.exe' : 'chromedriver';
    }

    private function extractBinary(string $zipPath, string $driversDir): void
    {
        if (!extension_loaded('zip')) {
            $this->extractWithCli($zipPath, $driversDir);

            return;
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Failed to open ChromeDriver zip archive.');
        }

        $binaryName = $this->binaryName();

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false) {
                continue;
            }

            if (basename($name) === $binaryName && !str_ends_with($name, '/')) {
                $content = $zip->getFromIndex($i);

                if ($content === false) {
                    throw new RuntimeException('Failed to read ChromeDriver from zip.');
                }

                file_put_contents($driversDir . DIRECTORY_SEPARATOR . $binaryName, $content);
                break;
            }
        }

        $zip->close();
    }

    private function extractWithCli(string $zipPath, string $driversDir): void
    {
        $binaryName = $this->binaryName();
        $output     = [];
        $code       = 0;

        if (PHP_OS_FAMILY === 'Windows') {
            @exec(
                sprintf('powershell -Command "Expand-Archive -Path \'%s\' -DestinationPath \'%s\' -Force"', $zipPath, sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cdtmp'),
                $output,
                $code,
            );
            $extracted = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cdtmp';
        } else {
            $extracted = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cdtmp_' . uniqid();
            @exec(sprintf('unzip -o %s -d %s 2>/dev/null', escapeshellarg($zipPath), escapeshellarg($extracted)), $output, $code);
        }

        $found = $this->findFileRecursive($extracted, $binaryName);

        if ($found === null) {
            throw new RuntimeException('Could not find chromedriver binary after extraction.');
        }

        copy($found, $driversDir . DIRECTORY_SEPARATOR . $binaryName);
        $this->removeDirectory($extracted);
    }

    private function fetchUrl(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout'         => 30,
                'follow_location' => 1,
                'user_agent'      => 'myerscode/beacon ChromeDriverInstaller',
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            throw new RuntimeException(sprintf('Failed to fetch URL: %s', $url));
        }

        return $content;
    }

    private function findFileRecursive(string $dir, string $filename): ?string
    {
        if (!is_dir($dir)) {
            return null;
        }

        $items = scandir($dir);

        if ($items === false) {
            return null;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $found = $this->findFileRecursive($path, $filename);

                if ($found !== null) {
                    return $found;
                }
            } elseif ($item === $filename) {
                return $path;
            }
        }

        return null;
    }

    private function platform(): string
    {
        $os   = PHP_OS_FAMILY;
        $arch = php_uname('m');

        if ($os === 'Windows') {
            return PHP_INT_SIZE === 8 ? 'win64' : 'win32';
        }

        if ($os === 'Darwin') {
            return str_contains($arch, 'arm') ? 'mac-arm64' : 'mac-x64';
        }

        return 'linux64';
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    /**
     * Resolve the download URL for the matching ChromeDriver version.
     * Uses Chrome for Testing API for Chrome 115+, legacy storage for older versions.
     *
     * @return array{string, string|null} [url, optional notice message]
     */
    private function resolveDownloadUrl(int $chromeMajor, string $chromeVersion): array
    {
        $platform = $this->platform();

        if ($chromeMajor >= 115) {
            return $this->resolveModernDownloadUrl($chromeMajor, $chromeVersion, $platform);
        }

        return [$this->resolveLegacyDownloadUrl($chromeMajor, $platform), null];
    }

    private function resolveDriversDir(?string $dir = null): string
    {
        $dir = $dir ?? self::DRIVERS_DIR;

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException(sprintf('Could not create drivers directory: %s', $dir));
        }

        return $dir;
    }

    private function resolveLegacyDownloadUrl(int $chromeMajor, string $platform): string
    {
        $latestUrl     = self::CHROMEDRIVER_LATEST_URL . '_' . $chromeMajor;
        $latestVersion = trim($this->fetchUrl($latestUrl));

        $platformMap = [
            'linux64'   => 'linux64',
            'mac-x64'   => 'mac64',
            'mac-arm64' => 'mac_arm64',
            'win32'     => 'win32',
            'win64'     => 'win32',
        ];

        $legacyPlatform = $platformMap[$platform] ?? $platform;

        return sprintf(
            'https://chromedriver.storage.googleapis.com/%s/chromedriver_%s.zip',
            $latestVersion,
            $legacyPlatform,
        );
    }

    /**
     * @return array{string, string|null}
     */
    private function resolveModernDownloadUrl(int $chromeMajor, string $chromeVersion, string $platform): array
    {
        $json = $this->fetchUrl(self::CHROME_FOR_TESTING_VERSIONS_URL);
        $data = json_decode($json, true);

        if (!is_array($data) || !isset($data['versions'])) {
            throw new RuntimeException('Failed to parse Chrome for Testing versions JSON.');
        }

        $bestUrl     = null;
        $bestVersion = null;

        foreach ($data['versions'] as $entry) {
            $entryVersion = $entry['version'] ?? '';
            $entryMajor   = (int) explode('.', $entryVersion)[0];

            if ($entryMajor !== $chromeMajor) {
                continue;
            }

            $downloads = $entry['downloads']['chromedriver'] ?? [];

            foreach ($downloads as $download) {
                if (($download['platform'] ?? '') === $platform) {
                    if ($entryVersion === $chromeVersion) {
                        return [$download['url'], null];
                    }

                    $bestUrl     = $download['url'];
                    $bestVersion = $entryVersion;
                }
            }
        }

        if ($bestUrl !== null) {
            return [$bestUrl, sprintf('Exact version match not found, using closest: %s', $bestVersion)];
        }

        throw new RuntimeException(
            sprintf('No ChromeDriver download found for Chrome %d on platform "%s".', $chromeMajor, $platform),
        );
    }

    /**
     * @return string[]
     */
    private function unixChromePaths(): array
    {
        $paths = [
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/Applications/Chromium.app/Contents/MacOS/Chromium',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/local/bin/chromium',
            '/snap/bin/chromium',
        ];

        $envPath = getenv('CHROME_PATH');

        if ($envPath !== false && $envPath !== '') {
            array_unshift($paths, $envPath);
        }

        return $paths;
    }

    /**
     * @return string[]
     */
    private function windowsChromePaths(): array
    {
        $paths   = [];
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
        ];

        foreach ($prefixes as $prefix) {
            foreach ($suffixes as $suffix) {
                $paths[] = $prefix . '\\' . $suffix;
            }
        }

        return $paths;
    }
}
