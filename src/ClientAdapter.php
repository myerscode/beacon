<?php

declare(strict_types=1);

namespace Myerscode\Beacon;

use Symfony\Component\Panther\Client;

class ClientAdapter implements ClientInterface
{
    public function __construct(private readonly Client $client)
    {
    }

    public function getCrawler(): CrawlerInterface
    {
        return new CrawlerAdapter($this->client->getCrawler());
    }

    public function getCurrentURL(): string
    {
        return $this->client->getCurrentURL();
    }

    public function getPageSource(): string
    {
        return $this->client->getPageSource();
    }

    public function getTitle(): string
    {
        return $this->client->getTitle();
    }

    public function quit(): void
    {
        $this->client->quit();
    }

    public function request(string $method, string $uri): void
    {
        $this->client->request($method, $uri);
    }

    public function takeScreenshot(string $path): void
    {
        $this->client->takeScreenshot($path);
    }

    public function waitFor(string $selector, int $timeout = 30): void
    {
        $this->client->waitFor($selector, $timeout);
    }

    public function waitForPageReady(int $timeout = 30): void
    {
        $this->client->waitFor('body', $timeout);

        // Wait for document.readyState to be 'complete' and body to have content
        $deadline = time() + $timeout;

        while (time() < $deadline) {
            /** @var string $state */
            $state = $this->client->executeScript('return document.readyState');

            if ($state === 'complete') {
                // Check body has actual content (not just an empty shell)
                /** @var int $bodyLength */
                $bodyLength = $this->client->executeScript('return document.body.innerHTML.length');

                if ($bodyLength > 0) {
                    return;
                }
            }

            usleep(200000);
        }
    }
}
