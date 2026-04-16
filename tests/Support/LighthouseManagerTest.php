<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Support;

use Myerscode\Beacon\Support\InstallationResult;
use Myerscode\Beacon\Support\LighthouseManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

final class LighthouseManagerTest extends TestCase
{
    // --- findLighthouse() ---

    public function testFindLighthouseReturnsNullOrString(): void
    {
        $result = $this->callMethod('findLighthouse');

        if ($result !== null) {
            $this->assertIsString($result);
            $this->assertNotEmpty($result);
        } else {
            $this->assertNull($result);
        }
    }
    // --- findNpm() ---

    public function testFindNpmReturnsStringOrThrows(): void
    {
        try {
            $npm = $this->callMethod('findNpm');
            $this->assertIsString($npm);
            $this->assertNotEmpty($npm);
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('npm not found', $e->getMessage());
        }
    }

    public function testGetBinaryVersionParsesOutput(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Shell script fake binary not supported on Windows.');
        }

        $tmpDir = sys_get_temp_dir() . '/beacon_lh_test_' . uniqid();
        mkdir($tmpDir, 0755, true);
        $binary = $tmpDir . '/lighthouse';

        file_put_contents($binary, "#!/bin/sh\necho '12.3.0'\n");
        chmod($binary, 0755);

        $version = $this->callMethod('getBinaryVersion', $binary);

        $this->assertSame('12.3.0', $version);

        unlink($binary);
        rmdir($tmpDir);
    }

    // --- getBinaryVersion() ---

    public function testGetBinaryVersionReturnsNullForMissingBinary(): void
    {
        $version = $this->callMethod('getBinaryVersion', '/nonexistent/lighthouse');

        $this->assertNull($version);
    }

    // --- install() ---

    public function testInstallReturnsSkippedWhenAlreadyInstalled(): void
    {
        $manager = $this->getMockBuilder(LighthouseManager::class)
            ->onlyMethods(['findLighthouse', 'getBinaryVersion'])
            ->getMock();

        $manager->expects($this->atLeastOnce())
            ->method('findLighthouse')
            ->willReturn('/usr/local/bin/lighthouse');

        $manager->expects($this->once())
            ->method('getBinaryVersion')
            ->with('/usr/local/bin/lighthouse')
            ->willReturn('12.3.0');

        $result = $manager->install(force: false);

        $this->assertInstanceOf(InstallationResult::class, $result);
        $this->assertSame(InstallationResult::STATUS_SKIPPED, $result->status);
        $this->assertTrue($result->isSkipped());
        $this->assertStringContainsStringIgnoringCase('Skipping', $result->summary);
    }

    // --- remove() ---

    public function testRemoveReturnsNothingWhenNotInstalled(): void
    {
        $manager = $this->getMockBuilder(LighthouseManager::class)
            ->onlyMethods(['findLighthouse'])
            ->getMock();

        $manager->expects($this->once())
            ->method('findLighthouse')
            ->willReturn(null);

        $result = $manager->remove();

        $this->assertInstanceOf(InstallationResult::class, $result);
        $this->assertSame(InstallationResult::STATUS_NOTHING, $result->status);
        $this->assertStringContainsStringIgnoringCase('not installed', $result->summary);
    }

    // --- helper ---

    private function callMethod(string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod(LighthouseManager::class, $method);

        return $ref->invoke(new LighthouseManager(), ...$args);
    }
}
