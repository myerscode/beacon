<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Driver;

interface HttpClientInterface
{
    /**
     * Fetch the contents of a URL.
     *
     * @throws \RuntimeException If the request fails.
     */
    public function fetch(string $url): string;
}
