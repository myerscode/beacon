<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Crawler;

use Myerscode\Beacon\ChromeDriverManager;
use Symfony\Component\Panther\Client;
use Throwable;

class SpiderCrawler
{
    private readonly CrawlResultCollection $crawlResultCollection;

    private string $baseHost = '';

    private string $baseScheme = '';

    private string $baseUrl = '';

    public function __construct(
        private readonly CrawlConfig $crawlConfig,
        private readonly ChromeDriverManager $chromeDriverManager,
    ) {
        $this->crawlResultCollection = new CrawlResultCollection();
    }

    /**
     * Crawl starting from an already-loaded page's HTML.
     *
     * @param string $startUrl The URL of the already-loaded page
     * @param string $html     The rendered HTML to extract seed links from
     */
    public function crawl(string $startUrl, string $html): CrawlResultCollection
    {
        $parsed           = parse_url($startUrl);
        $this->baseScheme = $parsed['scheme'] ?? 'https';
        $this->baseHost   = $parsed['host'] ?? '';
        $this->baseUrl    = sprintf('%s://%s', $this->baseScheme, $this->baseHost);

        $normalizedStart = $this->normalizeUrl($startUrl);

        // Record the start page as already visited
        $startResult = new CrawlResult($normalizedStart, true, 200, [], 0);
        $this->crawlResultCollection->add($startResult);
        $this->crawlConfig->notifyCrawled($normalizedStart, $startResult);

        /** @var array<string, bool> $queued */
        $queued = [$normalizedStart => true];

        // Extract and resolve seed links from the HTML
        $seedLinks = $this->extractLinksFromHtml($html, $normalizedStart);

        /** @var array<int, array{url: string, depth: int, source: string}> $queue */
        $queue = [];

        foreach ($seedLinks as $seedLink) {
            $normalized = $this->normalizeUrl($seedLink['url']);

            if ($normalized === '') {
                continue;
            }

            $isInternal = $this->isInternal($normalized);

            if (!$isInternal) {
                $externalResult = new CrawlResult($normalized, false, null, [$normalizedStart], 1);
                $this->crawlResultCollection->add($externalResult);
                $this->crawlConfig->notifyCrawled($normalized, $externalResult);
                continue;
            }

            if (isset($queued[$normalized])) {
                continue;
            }

            if (!$this->crawlConfig->isAllowed($normalized)) {
                continue;
            }

            if (1 > $this->crawlConfig->getMaxDepth()) {
                continue;
            }

            $queued[$normalized] = true;
            $queue[] = ['url' => $normalized, 'depth' => 1, 'source' => $normalizedStart];
        }

        if ($queue === []) {
            return $this->crawlResultCollection;
        }

        $maxConcurrent = $this->crawlConfig->getMaxConcurrent();

        /** @var Client[] $clients */
        $clients = [];
        for ($i = 0; $i < $maxConcurrent; $i++) {
            $clients[] = $this->chromeDriverManager->createClient();
        }

        try {
            $this->processQueue($queue, $queued, $clients);
        } finally {
            foreach ($clients as $client) {
                try {
                    $client->quit();
                } catch (Throwable) {
                    // Driver manager owns the cleanup
                }
            }
        }

        return $this->crawlResultCollection;
    }

    /**
     * Extract link hrefs from HTML and resolve them.
     *
     * @return array<int, array{url: string}>
     */
    private function extractLinksFromHtml(string $html, string $pageUrl): array
    {
        /** @var array<int, array{url: string}> $links */
        $links = [];

        if (preg_match_all('/<a\s[^>]*href=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $href) {
                $resolved = $this->resolveUrl($href, $pageUrl);

                if ($resolved !== '') {
                    $links[] = ['url' => $resolved];
                }
            }
        }

        return $links;
    }

    /**
     * @param array<int, array{url: string, depth: int, source: string}> $queue
     * @param array<string, bool>                                        $queued
     * @param Client[]                                                   $clients
     */
    private function processQueue(array &$queue, array &$queued, array $clients): void
    {
        $maxConcurrent = count($clients);

        while ($queue !== []) {
            // Grab a batch from the queue
            $batch = array_splice($queue, 0, $maxConcurrent);

            foreach ($batch as $index => $item) {
                $client = $clients[$index] ?? $clients[0];
                $newLinks = $this->visitPage($client, $item['url'], $item['depth'], $item['source']);

                // Add newly discovered links to the queue
                foreach ($newLinks as $newLink) {
                    $normalized = $this->normalizeUrl($newLink['url']);

                    if ($normalized === '') {
                        continue;
                    }

                    $isInternal = $this->isInternal($normalized);
                    $linkDepth  = $item['depth'] + 1;

                    if (!$isInternal) {
                        // Record external link but don't crawl it
                        $externalResult = new CrawlResult(
                            $normalized,
                            false,
                            null,
                            [$item['url']],
                            $linkDepth,
                        );
                        $this->crawlResultCollection->add($externalResult);
                        $this->crawlConfig->notifyCrawled($normalized, $externalResult);
                        continue;
                    }

                    if (isset($queued[$normalized])) {
                        // Already seen — just add the source reference
                        $existing = $this->crawlResultCollection->get($normalized);
                        if ($existing !== null) {
                            $this->crawlResultCollection->add($existing->withLinkedFrom($item['url']));
                        }

                        continue;
                    }

                    if ($linkDepth > $this->crawlConfig->getMaxDepth()) {
                        continue;
                    }

                    if (!$this->crawlConfig->isAllowed($normalized)) {
                        continue;
                    }

                    $queued[$normalized] = true;
                    $queue[] = ['url' => $normalized, 'depth' => $linkDepth, 'source' => $item['url']];
                }
            }
        }
    }

    /**
     * @return array<int, array{url: string}>
     */
    private function visitPage(Client $client, string $url, int $depth, string $source): array
    {
        $statusCode = null;
        $links      = [];

        try {
            $client->request('GET', $url);
            $this->waitForPageReady($client);
            $statusCode = $this->getStatusCode($client);

            $html  = $client->getPageSource();
            $links = $this->extractLinksFromHtml($html, $url);
        } catch (Throwable) {
            // Page failed to load — record with null status
        }

        $linkedFrom = $source !== '' ? [$source] : [];

        $this->crawlResultCollection->add(new CrawlResult(
            $url,
            $this->isInternal($url),
            $statusCode,
            $linkedFrom,
            $depth,
        ));

        $result = $this->crawlResultCollection->get($url);

        if ($result !== null) {
            $this->crawlConfig->notifyCrawled($url, $result);
        }

        return $links;
    }

    private function waitForPageReady(Client $client): void
    {
        $deadline = time() + $this->crawlConfig->getTimeout();

        while (time() < $deadline) {
            /** @var string $state */
            $state = $client->executeScript('return document.readyState');

            if ($state === 'complete') {
                /** @var int $bodyLength */
                $bodyLength = $client->executeScript('return document.body.innerHTML.length');

                if ($bodyLength > 0) {
                    return;
                }
            }

            usleep(200000);
        }
    }

    private function getStatusCode(Client $client): int
    {
        try {
            /** @var int $code */
            $code = $client->executeScript('return window.performance.getEntriesByType("navigation")[0]?.responseStatus || 200');

            return (int) $code;
        } catch (Throwable) {
            return 200;
        }
    }

    private function isInternal(string $url): bool
    {
        $parsed = parse_url($url);
        $host   = $parsed['host'] ?? '';

        return $host === $this->baseHost;
    }

    private function resolveUrl(string $href, string $currentPageUrl): string
    {
        $href = trim($href);

        // Skip non-http links
        if (str_starts_with($href, 'mailto:')
            || str_starts_with($href, 'tel:')
            || str_starts_with($href, 'javascript:')
            || str_starts_with($href, '#')
            || $href === ''
        ) {
            return '';
        }

        // Protocol-relative
        if (str_starts_with($href, '//')) {
            return sprintf('%s:%s', $this->baseScheme, $href);
        }

        // Absolute URL
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $this->stripFragment($href);
        }

        // Root-relative
        if (str_starts_with($href, '/')) {
            return $this->stripFragment($this->baseUrl . $href);
        }

        // Relative URL — resolve against current page
        $basePath = $this->getDirectoryPath($currentPageUrl);

        return $this->stripFragment(sprintf('%s%s/%s', $this->baseUrl, $basePath, $href));
    }

    private function getDirectoryPath(string $url): string
    {
        $parsed = parse_url($url);
        $path   = $parsed['path'] ?? '/';

        // Get directory portion of the path
        $dir = dirname($path);

        return $dir === '.' ? '/' : $dir;
    }

    private function normalizeUrl(string $url): string
    {
        return $this->stripFragment(rtrim($url, '/'));
    }

    private function stripFragment(string $url): string
    {
        $pos = strpos($url, '#');

        return $pos !== false ? substr($url, 0, $pos) : $url;
    }
}
