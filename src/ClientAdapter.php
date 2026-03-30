<?php

declare(strict_types=1);

namespace Myerscode\Beacon;

use Symfony\Component\Panther\Client;
use Throwable;

/**
 * @internal
 */
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

    public function getStatusCode(): int
    {
        try {
            /** @var int $code */
            $code = $this->client->executeScript(
                'return window.performance.getEntriesByType("navigation")[0]?.responseStatus || 200',
            );

            return (int) $code;
        } catch (Throwable) {
            return 200;
        }
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

    public function savePdf(string $path): void
    {
        /** @var array{data: string} $result */
        $result  = $this->client->execute('sendCommand', [
            'cmd'    => 'Page.printToPDF',
            'params' => [
                'printBackground'    => true,
                'preferCSSPageSize'  => true,
            ],
        ]);

        file_put_contents($path, base64_decode($result['data']));
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

        $deadline = time() + $timeout;

        while (time() < $deadline) {
            /** @var array{ready: bool, hasContent: bool} $pageState */
            $pageState = $this->client->executeScript(
                'return { ready: document.readyState === "complete", hasContent: document.body.innerHTML.length > 0 }',
            );

            if ($pageState['ready'] && $pageState['hasContent']) {
                return;
            }

            // Single executeScript call per iteration provides natural pacing (~10-50ms round-trip)
        }
    }
}
