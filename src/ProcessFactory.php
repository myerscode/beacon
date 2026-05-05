<?php

declare(strict_types=1);

namespace Myerscode\Beacon;

use Symfony\Component\Process\Process;

class ProcessFactory
{
    /**
     * Create a new Process for the given command.
     *
     * @param string[] $command
     * @param array<string, string>|null $env
     */
    public function create(array $command, ?array $env = null): Process
    {
        return new Process($command, null, $env, null, null);
    }
}
