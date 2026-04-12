<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Support;

use Myerscode\Beacon\Support\InstallationResult;
use PHPUnit\Framework\TestCase;

final class InstallationResultTest extends TestCase
{
    public function testSkippedFactory(): void
    {
        $result = InstallationResult::skipped('Already done.', 'Step 1');

        $this->assertSame(InstallationResult::STATUS_SKIPPED, $result->status);
        $this->assertSame('Already done.', $result->summary);
        $this->assertSame(['Step 1'], $result->messages);
        $this->assertTrue($result->isSkipped());
        $this->assertFalse($result->successful());
        $this->assertTrue($result->ok());
    }

    public function testSuccessFactory(): void
    {
        $result = InstallationResult::success('Installed.', 'Downloading...', 'Extracting...');

        $this->assertSame(InstallationResult::STATUS_SUCCESS, $result->status);
        $this->assertSame('Installed.', $result->summary);
        $this->assertSame(['Downloading...', 'Extracting...'], $result->messages);
        $this->assertFalse($result->isSkipped());
        $this->assertTrue($result->successful());
        $this->assertTrue($result->ok());
    }

    public function testRemovedFactory(): void
    {
        $result = InstallationResult::removed('Removed.');

        $this->assertSame(InstallationResult::STATUS_REMOVED, $result->status);
        $this->assertTrue($result->ok());
        $this->assertFalse($result->isSkipped());
        $this->assertFalse($result->successful());
    }

    public function testNothingFactory(): void
    {
        $result = InstallationResult::nothing('Nothing to do.');

        $this->assertSame(InstallationResult::STATUS_NOTHING, $result->status);
        $this->assertTrue($result->ok());
        $this->assertFalse($result->isSkipped());
        $this->assertFalse($result->successful());
    }

    public function testOutputReturnsMessagesAndSummary(): void
    {
        $result = InstallationResult::success('Done.', 'Step A', 'Step B');

        $this->assertSame("Step A\nStep B\nDone.\n", $result->output());
    }

    public function testOutputWithNoMessages(): void
    {
        $result = InstallationResult::skipped('Skipped.');

        $this->assertSame("Skipped.\n", $result->output());
    }
}
