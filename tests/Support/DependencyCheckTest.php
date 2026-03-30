<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Support;

use Myerscode\Beacon\Support\DependencyCheck;
use PHPUnit\Framework\TestCase;

final class DependencyCheckTest extends TestCase
{
    public function testNullableProperties(): void
    {
        $dependencyCheck = new DependencyCheck('Missing', false, null, null, 'Not found');

        $this->assertNull($dependencyCheck->path);
        $this->assertNull($dependencyCheck->version);
    }

    public function testOkReturnsFalseWhenNotFound(): void
    {
        $dependencyCheck = new DependencyCheck('Test', false, null, null, 'Not found');

        $this->assertFalse($dependencyCheck->ok());
    }
    public function testOkReturnsTrueWhenFound(): void
    {
        $dependencyCheck = new DependencyCheck('Test', true, '/usr/bin/test', '1.0.0', 'Found');

        $this->assertTrue($dependencyCheck->ok());
    }

    public function testProperties(): void
    {
        $dependencyCheck = new DependencyCheck('Chrome', true, '/usr/bin/chrome', '120.0', 'Found at /usr/bin/chrome');

        $this->assertSame('Chrome', $dependencyCheck->name);
        $this->assertTrue($dependencyCheck->found);
        $this->assertSame('/usr/bin/chrome', $dependencyCheck->path);
        $this->assertSame('120.0', $dependencyCheck->version);
        $this->assertSame('Found at /usr/bin/chrome', $dependencyCheck->message);
    }
}
