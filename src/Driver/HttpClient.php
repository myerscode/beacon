<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Driver;

use RuntimeException;

class HttpClient implements HttpClientInterface
{
    public function fetch(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'follow_location' => 1,
                'user_agent' => 'myerscode/beacon ChromeDriverInstaller',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            throw new RuntimeException(sprintf('Failed to fetch URL: %s', $url));
        }

        return $content;
    }
}
