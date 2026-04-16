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

        $result = (new ChromeDriverInstaller())->clean($this->tmpDir);

        $this->assertInstanceOf(InstallationResult::class, $result);
        $this->assertSame(InstallationResult::STATUS_REMOVED, $result->status);
        $this->assertStringContainsString('removed', $result->summary);
        $this->assertFileDoesNotExist($binary);
    }

    public function testCleanIsNoopWhenNoBinaryPresent(): void
    {
        $result = (new ChromeDriverInstaller())->clean($this->tmpDir);

        $this->assertInstanceOf(InstallationResult::class, $result);
        $this->assertSame(InstallationResult::STATUS_NOTHING, $result->status);
        $this->assertStringContainsString('nothing to clean', $result->summary);
    }

    // --- install() skip logic ---

    public function testInstallSkipsWhenVersionAlreadyMatchesChrome(): void
    {
        $installer = $this->getMockBuilder(ChromeDriverInstaller::class)
            ->onlyMethods(['getBinaryVersion', 'getChromeVersion'])
            ->getMock();

        $installer->expects($this->atLeastOnce())
            ->method('getBinaryVersion')
            ->willReturn('120.0.0.0');

        $installer->expects($this->atLeastOnce())
            ->method('getChromeVersion')
            ->willReturn('120.0.6099.109');

        $binary = $this->tmpDir . DIRECTORY_SEPARATOR . $this->binaryName();
        file_put_contents($binary, 'fake binary');

        $result = $installer->install(force: false, driversDir: $this->tmpDir);

        $this->assertSame(InstallationResult::STATUS_SKIPPED, $result->status);
        $this->assertTrue($result->isSkipped());
        $this->assertStringContainsStringIgnoringCase('Skipping', $result->summary);
    }

    public function testInstallWithForceFlagBypassesVersionCheck(): void
    {
        $installer = $this->getMockBuilder(ChromeDriverInstaller::class)
            ->onlyMethods(['getBinaryVersion', 'getChromeVersion', 'download'])
            ->getMock();

        $installer->expects($this->never())
            ->method('getChromeVersion');

        $installer->expects($this->once())
            ->method('download')
            ->with($this->tmpDir)
            ->willReturn(InstallationResult::success('ChromeDriver installed'));

        $result = $installer->install(force: true, driversDir: $this->tmpDir);

        $this->assertTrue($result->successful());
        $this->assertNotSame(InstallationResult::STATUS_SKIPPED, $result->status);
    }

    public function testInstallProceedsWhenVersionMismatches(): void
    {
        $installer = $this->getMockBuilder(ChromeDriverInstaller::class)
            ->onlyMethods(['getBinaryVersion', 'getChromeVersion', 'download'])
            ->getMock();

        $installer->expects($this->atLeastOnce())
            ->method('getBinaryVersion')
            ->willReturn('119.0.0.0');

        $installer->expects($this->atLeastOnce())
            ->method('getChromeVersion')
            ->willReturn('120.0.6099.109');

        $installer->expects($this->once())
            ->method('download')
            ->with($this->tmpDir)
            ->willReturn(InstallationResult::success('ChromeDriver installed'));

        $binary = $this->tmpDir . DIRECTORY_SEPARATOR . $this->binaryName();
        file_put_contents($binary, 'fake binary');

        $result = $installer->install(force: false, driversDir: $this->tmpDir);

        $this->assertTrue($result->successful());
        $this->assertNotSame(InstallationResult::STATUS_SKIPPED, $result->status);
    }

    // --- platform() ---

    public function testPlatformReturnsKnownValue(): void
    {
        $platform = $this->callMethod('platform');

        $this->assertContains($platform, ['linux64', 'mac-x64', 'mac-arm64', 'win32', 'win64']);
    }

    // --- binaryName() ---

    public function testBinaryNameIsCorrectForCurrentOs(): void
    {
        $name     = $this->callMethod('binaryName');
        $expected = PHP_OS_FAMILY === 'Windows' ? 'chromedriver.exe' : 'chromedriver';

        $this->assertSame($expected, $name);
    }

    // --- getChromeVersion() ---

    public function testGetChromeVersionReturnsVersionWhenChromeFound(): void
    {
        $installer = $this->getMockBuilder(ChromeDriverInstaller::class)
            ->onlyMethods(['getBinaryVersion'])
            ->getMock();

        $installer->expects($this->atLeastOnce())
            ->method('getBinaryVersion')
            ->willReturn('120.0.6099.109');

        $version = $installer->getChromeVersion();

        $this->assertSame('120.0.6099.109', $version);
    }

    public function testGetChromeVersionReturnsNullWhenNoChromeFound(): void
    {
        $installer = $this->getMockBuilder(ChromeDriverInstaller::class)
            ->onlyMethods(['getBinaryVersion'])
            ->getMock();

        $installer->expects($this->atLeastOnce())
            ->method('getBinaryVersion')
            ->willReturn(null);

        $this->assertNull($installer->getChromeVersion());
    }

    // --- getBinaryVersion() ---

    public function testGetBinaryVersionReturnsNullForMissingBinary(): void
    {
        $version = $this->callMethod('getBinaryVersion', '/nonexistent/path/chromedriver');

        $this->assertNull($version);
    }

    public function testGetBinaryVersionParsesVersionOutput(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Shell script fake binary not supported on Windows.');
        }

        $binary = $this->tmpDir . DIRECTORY_SEPARATOR . 'fake_chromedriver';
        $this->writeFakeBinary($binary, 'ChromeDriver 120.0.6099.109');

        $version = $this->callMethod('getBinaryVersion', $binary);

        $this->assertSame('120.0.6099.109', $version);
    }

    // --- resolveDriversDir() ---

    public function testResolveDriversDirCreatesDirectoryIfMissing(): void
    {
        $newDir = $this->tmpDir . '/subdir_' . uniqid();
        $this->assertDirectoryDoesNotExist($newDir);

        $this->callMethod('resolveDriversDir', $newDir);

        $this->assertDirectoryExists($newDir);
    }

    public function testResolveDriversDirReturnsExistingDir(): void
    {
        $result = $this->callMethod('resolveDriversDir', $this->tmpDir);

        $this->assertSame($this->tmpDir, $result);
    }

    // --- helpers ---

    private function callMethod(string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod(ChromeDriverInstaller::class, $method);

        return $ref->invoke(new ChromeDriverInstaller(), ...$args);
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
