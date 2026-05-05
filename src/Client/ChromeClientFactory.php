<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Client;

use Myerscode\Beacon\Driver\ChromeDriverManager;

/**
 * Default ClientFactory that creates Chrome sessions via a local ChromeDriverManager.
 */
class ChromeClientFactory implements ClientFactory
{
    public function __construct(
        private readonly ChromeDriverManager $driver,
    ) {
    }

    public function create(): ClientInterface
    {
        return new ClientAdapter($this->driver->createClient());
    }
}
