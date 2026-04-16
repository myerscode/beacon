<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Support;

use Myerscode\Beacon\Support\InstallationResult;
use Myerscode\Beacon\Support\LighthouseManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TestableLighthouseManager extends LighthouseManager
{
    public function callFindNpm(): string
    {
        return $this->findNpm();
    }

    public function callFindLighthouse(): ?string
    {
        return $this->findLighthouse();
    }

    public function callGetBinaryVersion(string $binary): ?string
    {
        return $this->getBinaryVersion($binary);
    }
}

final class LighthouseManagerTest extends TestCase
{
    private TestableLighthouseManager $manager;

    protected function setUp(): void
    {
        $this->manager = new TestableLighthouseManager();
    }

    // --- findNpm() ---

    public function testFindNpmReturnsStringOrThrows(): void
    {
        try {
            $npm = $this->manager->callFindNpm();
            $this->assertIsString($npm);
            $this->assertNotEmpty($npm);
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('npm not found', $e->getMessage());
        }
    }

    // --- findLighthouse() ---

    public function testFindLighthouseReturnsNullOrString(): void
    {
        $result = $this->manager->callFindLighthouse();

        if ($result !== null) {
            $this->assertIsString($result);
            $this->assertNotEmpty($result);
        } else {
            $this->assertNull($result);
        }
    }

    // --- getBinaryVersion() ---

    public function testGetBinaryVersionReturnsNullForMissingBinary(): void
    {
        $this->assertNull($this->manager->callGetBinaryVersion('/nonexistent/lighthouse'));
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

        $this->assertSame('12.3.0', $this->manager->callGetBinaryVersion($binary));

        unlink($binary);
        rmdir($tmpDir);
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
}
