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
    // --- findNpm() ---

    public function testFindNpmReturnsStringOrThrows(): void
    {
        try {
            $npm = $this->callStatic('findNpm');
            $this->assertIsString($npm);
            $this->assertNotEmpty($npm);
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('npm not found', $e->getMessage());
        }
    }

    // --- findLighthouse() ---

    public function testFindLighthouseReturnsNullOrString(): void
    {
        $result = $this->callStatic('findLighthouse');

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
        $version = $this->callStatic('getBinaryVersion', '/nonexistent/lighthouse');

        $this->assertNull($version);
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

        $version = $this->callStatic('getBinaryVersion', $binary);

        $this->assertSame('12.3.0', $version);

        unlink($binary);
        rmdir($tmpDir);
    }

    // --- install() ---

    public function testInstallReturnsSkippedWhenAlreadyInstalled(): void
    {
        $existing = $this->callStatic('findLighthouse');

        if ($existing === null) {
            $this->markTestSkipped('Lighthouse not installed in this environment.');
        }

        $result = LighthouseManager::install(force: false);

        $this->assertInstanceOf(InstallationResult::class, $result);
        $this->assertSame(InstallationResult::STATUS_SKIPPED, $result->status);
        $this->assertTrue($result->isSkipped());
        $this->assertStringContainsStringIgnoringCase('Skipping', $result->summary);
    }

    // --- remove() ---

    public function testRemoveReturnsNothingWhenNotInstalled(): void
    {
        $existing = $this->callStatic('findLighthouse');

        if ($existing !== null) {
            $this->markTestSkipped('Lighthouse is installed — skipping not-installed test.');
        }

        $result = LighthouseManager::remove();

        $this->assertInstanceOf(InstallationResult::class, $result);
        $this->assertSame(InstallationResult::STATUS_NOTHING, $result->status);
        $this->assertStringContainsStringIgnoringCase('not installed', $result->summary);
    }

    // --- helper ---

    private function callStatic(string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod(LighthouseManager::class, $method);

        return $ref->invoke(null, ...$args);
    }
}
