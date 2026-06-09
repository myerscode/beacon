<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Driver;

use Myerscode\Beacon\Driver\ProcessFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class ProcessFactoryTest extends TestCase
{
    public function testCreatesProcess(): void
    {
        $factory = new ProcessFactory();
        $process = $factory->create(['echo', 'hello']);

        $this->assertInstanceOf(Process::class, $process);
    }
}
