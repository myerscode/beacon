<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Crawler;

use Fiber;
use Myerscode\Beacon\Client\ClientFactory;
use Myerscode\Beacon\Client\ClientInterface;
use Throwable;

/**
 * @internal
 */
class Spider
{
    private string $baseHost = '';

    private string $baseScheme = '';

    private string $baseUrl = '';

    private readonly CrawlResultCollection $crawlResultCollection;

    public function __construct(
        private readonly CrawlConfig $crawlConfig,
        private readonly ClientFactory $clientFactory,
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

        // Record assets from the start page (if media discovery is enabled)
        if ($this->crawlConfig->shouldDiscoverMedia()) {
            $this->recordAssets($html, $normalizedStart, 0);
        }

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

        // Add seed URLs to the queue (pre-loaded known pages)
        foreach ($this->crawlConfig->getSeeds() as $seedUrl) {
            $normalized = $this->normalizeUrl($seedUrl);

            if ($normalized === '' || isset($queued[$normalized])) {
                continue;
            }

            if (!$this->isInternal($normalized)) {
                continue;
            }

            if (!$this->crawlConfig->isAllowed($normalized)) {
                continue;
            }

            $queued[$normalized] = true;
            $queue[] = ['url' => $normalized, 'depth' => 1, 'source' => ''];
        }

        if ($queue === []) {
            return $this->crawlResultCollection;
        }

        $maxConcurrent = $this->crawlConfig->getMaxConcurrent();

        /** @var ClientInterface[] $clients */
        $clients = [];

        for ($i = 0; $i < $maxConcurrent; $i++) {
            $clients[] = $this->clientFactory->create();
        }

        try {
            $this->processQueue($queue, $queued, $clients);
        } finally {
            foreach ($clients as $client) {
                try {
                    $client->quit();
                } catch (Throwable) {
                    // Best effort cleanup
                }
            }
        }

        return $this->crawlResultCollection;
    }

    /**
     * Process discovered links and add eligible ones to the queue.
     *
     * @param array<int, array{url: string}>                             $newLinks
     * @param array{url: string, depth: int, source: string}             $item
     * @param array<int, array{url: string, depth: int, source: string}> $queue
     * @param array<string, bool>                                        $queued
     */
    protected function enqueueLinks(array $newLinks, array $item, array &$queue, array &$queued): void
    {
        foreach ($newLinks as $newLink) {
            $normalized = $this->normalizeUrl($newLink['url']);

            if ($normalized === '') {
                continue;
            }

            $isInternal = $this->isInternal($normalized);
            $linkDepth  = $item['depth'] + 1;

            if (!$isInternal) {
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
            $queue[]             = ['url' => $normalized, 'depth' => $linkDepth, 'source' => $item['url']];
        }
    }

    /**
     * Extract static asset URLs from HTML (images, stylesheets, scripts, fonts).
     * These are recorded but never followed.
     *
     * @return array<int, array{url: string}>
     */
    protected function extractAssetsFromHtml(string $html, string $pageUrl): array
    {
        /** @var array<int, array{url: string}> $assets */
        $assets = [];

        // <img src="...">
        if (preg_match_all('/<img\s[^>]*src=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $src) {
                $resolved = $this->resolveUrl($src, $pageUrl);

                if ($resolved !== '') {
                    $assets[] = ['url' => $resolved];
                }
            }
        }

        // <link href="..."> (stylesheets, icons, etc.)
        if (preg_match_all('/<link\s[^>]*href=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $href) {
                $resolved = $this->resolveUrl($href, $pageUrl);

                if ($resolved !== '') {
                    $assets[] = ['url' => $resolved];
                }
            }
        }

        // <script src="...">
        if (preg_match_all('/<script\s[^>]*src=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $src) {
                $resolved = $this->resolveUrl($src, $pageUrl);

                if ($resolved !== '') {
                    $assets[] = ['url' => $resolved];
                }
            }
        }

        // <source src="..."> (video/audio)
        if (preg_match_all('/<source\s[^>]*src=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $src) {
                $resolved = $this->resolveUrl($src, $pageUrl);

                if ($resolved !== '') {
                    $assets[] = ['url' => $resolved];
                }
            }
        }

        return $assets;
    }

    /**
     * Extract link hrefs from HTML and resolve them.
     *
     * @return array<int, array{url: string}>
     */
    protected function extractLinksFromHtml(string $html, string $pageUrl): array
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

    protected function getDirectoryPath(string $url): string
    {
        $parsed = parse_url($url);
        $path   = $parsed['path'] ?? '/';

        // Get directory portion of the path (URL paths always use forward slashes)
        $lastSlash = strrpos($path, '/');

        if ($lastSlash === false || $lastSlash === 0) {
            return '/';
        }

        return substr($path, 0, $lastSlash);
    }

    protected function isInternal(string $url): bool
    {
        $parsed = parse_url($url);
        $host   = $parsed['host'] ?? '';

        return $host === $this->baseHost;
    }

    protected function normalizeUrl(string $url): string
    {
        return $this->stripFragment(rtrim($url, '/'));
    }

    protected function resolveUrl(string $href, string $currentPageUrl): string
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

    /**
     * @param array<int, array{url: string, depth: int, source: string}> $queue
     * @param array<string, bool>                                        $queued
     * @param ClientInterface[]                                          $clients
     */
    private function processQueue(array &$queue, array &$queued, array $clients): void
    {
        $maxConcurrent = count($clients);
        /** @var array<int, bool> $clientAvailable */
        $clientAvailable = array_fill(0, $maxConcurrent, true);

        /** @var array<int, Fiber<null, null, array<int, array{url: string}>, null>> $fibers */
        $fibers = [];

        /** @var array<int, array{url: string, depth: int, source: string}> $fiberItems */
        $fiberItems = [];

        while ($queue !== [] || $fibers !== []) {
            // Fill available client slots with queued items
            foreach ($clientAvailable as $index => $available) {
                if (!$available || $queue === []) {
                    continue;
                }

                $item   = array_shift($queue);
                $client = $clients[$index];

                $clientAvailable[$index] = false;
                $fiberItems[$index]      = $item;

                $fibers[$index] = new Fiber(function () use ($client, $item): array {
                    return $this->visitPageAsync($client, $item['url'], $item['depth'], $item['source']);
                });

                try {
                    $fibers[$index]->start();
                } catch (Throwable) {
                    $clientAvailable[$index] = true;
                    unset($fibers[$index], $fiberItems[$index]);
                }
            }

            // Round-robin through active Fibers
            foreach ($fibers as $index => $fiber) {
                try {
                    if ($fiber->isTerminated()) {
                        /** @var array<int, array{url: string}> $newLinks */
                        $newLinks = $fiber->getReturn();
                        $item     = $fiberItems[$index];

                        $this->enqueueLinks($newLinks, $item, $queue, $queued);

                        $clientAvailable[$index] = true;
                        unset($fibers[$index], $fiberItems[$index]);
                    } elseif ($fiber->isSuspended()) {
                        $fiber->resume();
                    }
                } catch (Throwable) {
                    // Fiber threw — release the client and move on
                    $clientAvailable[$index] = true;
                    unset($fibers[$index], $fiberItems[$index]);
                }
            }
        }
    }

    /**
     * Extract and record static assets from HTML without following them.
     */
    private function recordAssets(string $html, string $pageUrl, int $depth): void
    {
        $assets = $this->extractAssetsFromHtml($html, $pageUrl);

        foreach ($assets as $asset) {
            $normalized = $this->normalizeUrl($asset['url']);

            if ($normalized === '') {
                continue;
            }

            $isInternal = $this->isInternal($normalized);

            $assetResult = new CrawlResult(
                $normalized,
                $isInternal,
                null,
                [$pageUrl],
                $depth + 1,
                asset: true,
            );

            $this->crawlResultCollection->add($assetResult);
            $this->crawlConfig->notifyCrawled($normalized, $assetResult);
        }
    }

    private function stripFragment(string $url): string
    {
        $pos = strpos($url, '#');

        return $pos !== false ? substr($url, 0, $pos) : $url;
    }

    /**
     * Visit a page asynchronously, yielding while waiting for it to load.
     *
     * @return array<int, array{url: string}>
     */
    private function visitPageAsync(ClientInterface $client, string $url, int $depth, string $source): array
    {
        $statusCode  = null;
        $links       = [];
        $attempts    = 0;
        $maxAttempts = $this->crawlConfig->getMaxRetries() + 1;

        while ($attempts < $maxAttempts) {
            $attempts++;

            // Apply request delay with optional jitter
            $delay = $this->crawlConfig->calculateRequestDelay();

            if ($delay > 0) {
                Fiber::suspend();
                usleep($delay * 1000);
            }

            try {
                $client->request('GET', $url);
                $client->waitForPageReady($this->crawlConfig->getTimeout());

                $statusCode = $client->getStatusCode();
                $html       = $client->getPageSource();
                $links      = $this->extractLinksFromHtml($html, $url);

                // Record assets from this page (if media discovery is enabled)
                if ($this->crawlConfig->shouldDiscoverMedia()) {
                    $this->recordAssets($html, $url, $depth);
                }

                break;
            } catch (Throwable) {
                if ($attempts >= $maxAttempts) {
                    break;
                }

                // Apply retry delay with exponential backoff and optional jitter
                $retryDelay = $this->crawlConfig->calculateRetryDelay($attempts);

                if ($retryDelay > 0) {
                    Fiber::suspend();
                    usleep($retryDelay * 1000);
                } else {
                    Fiber::suspend();
                }
            }
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

}
