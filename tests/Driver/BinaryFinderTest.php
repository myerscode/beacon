<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Driver;

use Myerscode\Beacon\Driver\BinaryFinder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BinaryFinderTest extends TestCase
{
    public function testLocatesDriver(): void
    {
        $localBinary = __DIR__ . '/../../drivers/chromedriver';

        if (!file_exists($localBinary) || !is_executable($localBinary)) {
            $this->markTestSkipped('Local chromedriver binary not available');
        }

        $finder = new BinaryFinder();
        $result = $finder->find();

        $this->assertFileExists($result);
    }

    public function testThrowsWhenDriverNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"chromedriver" binary not found');

        $driversDir = __DIR__ . '/../../drivers';
        $tempDir = __DIR__ . '/../../drivers_backup_test';
        $renamed = false;

        if (is_dir($driversDir)) {
            rename($driversDir, $tempDir);
            $renamed = true;
        }

        $originalPath = getenv('PATH');
        putenv('PATH=/nonexistent');

        try {
            (new BinaryFinder())->find();
        } finally {
            putenv('PATH=' . $originalPath);

            if ($renamed) {
                rename($tempDir, $driversDir);
            }
        }
    }
}
