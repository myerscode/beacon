<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Support;

use RuntimeException;

/**
 * Manages installing, updating, and removing the Lighthouse CLI via npm.
 */
class LighthouseManager
{

    /**
     * Install Lighthouse CLI globally via npm.
     * Skips if already installed.
     */
    public function install(bool $force = false): InstallationResult
    {
        $npm = $this->findNpm();

        if (!$force) {
            $existing = $this->findLighthouse();

            if ($existing !== null) {
                $version = $this->getBinaryVersion($existing);

                return InstallationResult::skipped(
                    sprintf('Lighthouse %s already installed at %s. Skipping.', $version ?? 'unknown', $existing),
                );
            }
        }

        $npmOutput = $this->runNpm($npm, ['install', '-g', 'lighthouse']);
        $path      = $this->findLighthouse();

        return InstallationResult::success(
            sprintf('Lighthouse installed%s', $path !== null ? " at {$path}" : ''),
            ...$npmOutput,
        );
    }

    /**
     * Update Lighthouse CLI to the latest version.
     */
    public function update(): InstallationResult
    {
        $npm       = $this->findNpm();
        $npmOutput = $this->runNpm($npm, ['update', '-g', 'lighthouse']);

        $path    = $this->findLighthouse();
        $version = $path !== null ? $this->getBinaryVersion($path) : null;

        return InstallationResult::success(
            sprintf('Lighthouse updated%s', $version !== null ? " to v{$version}" : ''),
            ...$npmOutput,
        );
    }

    /**
     * Remove Lighthouse CLI global installation.
     */
    public function remove(): InstallationResult
    {
        $npm      = $this->findNpm();
        $existing = $this->findLighthouse();

        if ($existing === null) {
            return InstallationResult::nothing('Lighthouse is not installed, nothing to remove.');
        }

        $npmOutput = $this->runNpm($npm, ['uninstall', '-g', 'lighthouse']);

        return InstallationResult::removed('Lighthouse removed.', ...$npmOutput);
    }

    private function findNpm(): string
    {
        $command = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        $output  = [];
        $code    = 0;

        @exec(sprintf('%s npm 2>/dev/null', $command), $output, $code);

        if ($code !== 0 || !isset($output[0]) || $output[0] === '') {
            throw new RuntimeException(
                'npm not found. Install Node.js from https://nodejs.org/ to manage Lighthouse.',
            );
        }

        return trim($output[0]);
    }

    protected function findLighthouse(): ?string
    {
        $command = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        $output  = [];
        $code    = 0;

        @exec(sprintf('%s lighthouse 2>/dev/null', $command), $output, $code);

        if ($code === 0 && isset($output[0]) && $output[0] !== '') {
            return trim($output[0]);
        }

        return null;
    }

    protected function getBinaryVersion(string $binary): ?string
    {
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

    /**
     * @param string[] $args
     * @return string[]
     */
    private function runNpm(string $npm, array $args): array
    {
        $cmd    = implode(' ', array_map('escapeshellarg', [$npm, ...$args]));
        $output = [];
        $code   = 0;

        @exec($cmd . ' 2>&1', $output, $code);

        if ($code !== 0) {
            throw new RuntimeException(
                sprintf("npm command failed (exit %d):\n%s", $code, implode("\n", $output)),
            );
        }

        return $output;
    }
}
