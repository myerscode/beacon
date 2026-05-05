<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Support;

use Myerscode\Beacon\ProcessFactory;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Manages installing, updating, and removing the Lighthouse CLI via npm.
 */
class LighthouseManager
{
    public function __construct(
        private readonly ?ExecutableFinder $executableFinder = null,
        private readonly ?ProcessFactory $processFactory = null,
    ) {
    }

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
        $finder = $this->executableFinder ?? new ExecutableFinder();

        return $finder->find('lighthouse');
    }

    protected function findNpm(): string
    {
        $finder = $this->executableFinder ?? new ExecutableFinder();
        $path   = $finder->find('npm');

        if ($path === null) {
            throw new RuntimeException(
                'npm not found. Install Node.js from https://nodejs.org/ to manage Lighthouse.',
            );
        }

        return $path;
    }

    protected function getBinaryVersion(string $binary): ?string
    {
        try {
            $factory = $this->processFactory ?? new ProcessFactory();
            $process = $factory->create([$binary, '--version']);
            $process->setTimeout(5);
            $process->run();

            $output = trim($process->getOutput() ?: $process->getErrorOutput());

            if (preg_match('/(\d+\.\d+[\.\d]*)/', $output, $matches)) {
                return $matches[1];
            }
        } catch (Throwable) {
            // Binary not runnable
        }

        return null;
    }

    /**
     * @param string[] $args
     * @return string[]
     */
    private function runNpm(string $npm, array $args): array
    {
        $factory = $this->processFactory ?? new ProcessFactory();
        $process = $factory->create([$npm, ...$args]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                sprintf("npm command failed (exit %d):\n%s", $process->getExitCode(), $process->getErrorOutput()),
            );
        }

        return array_filter(explode("\n", $process->getOutput()));
    }
}
