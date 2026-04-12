<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests;

use Myerscode\Beacon\ChromeDriverInstaller;
use Myerscode\Beacon\Support\InstallationResult;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ChromeDriverInstallerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/beacon_test_drivers_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    // --- clean() ---

    public function testCleanRemovesBinary(): void
    {
        $binary = $this->tmpDir . DIRECTORY_SEPARATOR . $this->binaryName();
        file_put_contents($binary, 'fake binary');

        $result = ChromeDriverInstaller::clean($this->tmpDir);

        $this->assertInstanceOf(InstallationResult::class, $result);
        $this->assertSame(InstallationResult::STATUS_REMOVED, $result->status);
        $this->assertStringContainsString('removed', $result->summary);
        $this->assertFileDoesNotExist($binary);
    }

    public function testCleanIsNoopWhenNoBinaryPresent(): void
    {
        $result = ChromeDriverInstaller::clean($this->tmpDir);

        $this->assertInstanceOf(InstallationResult::class, $result);
        $this->assertSame(InstallationResult::STATUS_NOTHING, $result->status);
        $this->assertStringContainsString('nothing to clean', $result->summary);
    }

    // --- install() skip logic ---

    public function testInstallSkipsWhenVersionAlreadyMatchesChrome(): void
    {
        $chromeVersion = ChromeDriverInstaller::getChromeVersion();

        if ($chromeVersion === null) {
            $this->markTestSkipped('Chrome not available in this environment.');
        }

        $chromeMajor = (int) explode('.', $chromeVersion)[0];
        $binary      = $this->tmpDir . DIRECTORY_SEPARATOR . $this->binaryName();
        $this->writeFakeBinary($binary, "ChromeDriver {$chromeMajor}.0.0.0");

        $result = ChromeDriverInstaller::install(force: false, driversDir: $this->tmpDir);

        $this->assertSame(InstallationResult::STATUS_SKIPPED, $result->status);
        $this->assertTrue($result->isSkipped());
        $this->assertStringContainsStringIgnoringCase('Skipping', $result->summary);
    }

    public function testInstallWithForceFlagSkipsVersionCheck(): void
    {
        $chromeVersion = ChromeDriverInstaller::getChromeVersion();

        if ($chromeVersion === null) {
            $this->markTestSkipped('Chrome not available in this environment.');
        }

        $chromeMajor = (int) explode('.', $chromeVersion)[0];
        $binary      = $this->tmpDir . DIRECTORY_SEPARATOR . $this->binaryName();
        $this->writeFakeBinary($binary, "ChromeDriver {$chromeMajor}.0.0.0");

        try {
            $result = ChromeDriverInstaller::install(force: true, driversDir: $this->tmpDir);
            $this->assertNotSame(InstallationResult::STATUS_SKIPPED, $result->status);
        } catch (\RuntimeException) {
            // Network not available — acceptable in CI
            $this->assertTrue(true);
        }
    }

    public function testInstallProceedsWhenVersionMismatches(): void
    {
        $chromeVersion = ChromeDriverInstaller::getChromeVersion();

        if ($chromeVersion === null) {
            $this->markTestSkipped('Chrome not available in this environment.');
        }

        $binary = $this->tmpDir . DIRECTORY_SEPARATOR . $this->binaryName();
        $this->writeFakeBinary($binary, 'ChromeDriver 1.0.0.0');

        try {
            $result = ChromeDriverInstaller::install(force: false, driversDir: $this->tmpDir);
            $this->assertNotSame(InstallationResult::STATUS_SKIPPED, $result->status);
        } catch (\RuntimeException) {
            // Network not available — acceptable in CI
            $this->assertTrue(true);
        }
    }

    // --- platform() ---

    public function testPlatformReturnsKnownValue(): void
    {
        $platform = $this->callStatic('platform');

        $this->assertContains($platform, ['linux64', 'mac-x64', 'mac-arm64', 'win32', 'win64']);
    }

    // --- binaryName() ---

    public function testBinaryNameIsCorrectForCurrentOs(): void
    {
        $name     = $this->callStatic('binaryName');
        $expected = PHP_OS_FAMILY === 'Windows' ? 'chromedriver.exe' : 'chromedriver';

        $this->assertSame($expected, $name);
    }

    // --- getChromeVersion() ---

    public function testGetChromeVersionReturnsNullOrVersionString(): void
    {
        $version = ChromeDriverInstaller::getChromeVersion();

        if ($version !== null) {
            $this->assertMatchesRegularExpression('/^\d+\.\d+/', $version);
        } else {
            $this->assertNull($version);
        }
    }

    // --- getBinaryVersion() ---

    public function testGetBinaryVersionReturnsNullForMissingBinary(): void
    {
        $version = $this->callStatic('getBinaryVersion', '/nonexistent/path/chromedriver');

        $this->assertNull($version);
    }

    public function testGetBinaryVersionParsesVersionOutput(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Shell script fake binary not supported on Windows.');
        }

        $binary = $this->tmpDir . DIRECTORY_SEPARATOR . 'fake_chromedriver';
        $this->writeFakeBinary($binary, 'ChromeDriver 120.0.6099.109');

        $version = $this->callStatic('getBinaryVersion', $binary);

        $this->assertSame('120.0.6099.109', $version);
    }

    // --- resolveDriversDir() ---

    public function testResolveDriversDirCreatesDirectoryIfMissing(): void
    {
        $newDir = $this->tmpDir . '/subdir_' . uniqid();
        $this->assertDirectoryDoesNotExist($newDir);

        $this->callStatic('resolveDriversDir', $newDir);

        $this->assertDirectoryExists($newDir);
    }

    public function testResolveDriversDirReturnsExistingDir(): void
    {
        $result = $this->callStatic('resolveDriversDir', $this->tmpDir);

        $this->assertSame($this->tmpDir, $result);
    }

    // --- helpers ---

    private function callStatic(string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod(ChromeDriverInstaller::class, $method);

        return $ref->invoke(null, ...$args);
    }

    private function binaryName(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'chromedriver.exe' : 'chromedriver';
    }

    private function writeFakeBinary(string $path, string $versionOutput): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            file_put_contents($path, "@echo off\necho {$versionOutput}\n");
        } else {
            file_put_contents($path, "#!/bin/sh\necho '{$versionOutput}'\n");
            chmod($path, 0755);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
