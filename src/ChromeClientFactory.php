<?php

declare(strict_types=1);

namespace Myerscode\Beacon;

/**
 * Default ClientFactory that creates Chrome sessions via ChromeDriverManager.
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
