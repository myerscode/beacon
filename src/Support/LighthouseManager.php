<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Support;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

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

    protected function findLighthouse(): ?string
    {
        return (new ExecutableFinder())->find('lighthouse');
    }

    protected function getBinaryVersion(string $binary): ?string
    {
        try {
            $process = new Process([$binary, '--version']);
            $process->setTimeout(5);
            $process->run();

            $output = trim($process->getOutput() ?: $process->getErrorOutput());

            if (preg_match('/(\d+\.\d+[\.\d]*)/', $output, $matches)) {
                return $matches[1];
            }
        } catch (\Throwable) {
            // Binary not runnable
        }

        return null;
    }

    protected function findNpm(): string
    {
        $path = (new ExecutableFinder())->find('npm');

        if ($path === null) {
            throw new RuntimeException(
                'npm not found. Install Node.js from https://nodejs.org/ to manage Lighthouse.',
            );
        }

        return $path;
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
