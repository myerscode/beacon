<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Client;

interface ClientFactory
{
    public function create(): ClientInterface;
}
