<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Crawler;

use Closure;

class CrawlConfig
{
    /**
     * @var string[]
     */
    private array $excludePatterns = [];

    private int $maxConcurrent = 5;
    private int $maxDepth = 5;
    private int $maxRetries = 0;

    private ?Closure $onCrawled = null;

    private int $requestDelay = 0;

    private ?Closure $shouldCrawl = null;

    private int $timeout = 30;

    /**
     * @param string[] $patterns URL patterns to exclude (matched with str_contains)
     */
    public function exclude(array $patterns): self
    {
        $this->excludePatterns = $patterns;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getExcludePatterns(): array
    {
        return $this->excludePatterns;
    }

    public function getMaxConcurrent(): int
    {
        return $this->maxConcurrent;
    }

    public function getMaxDepth(): int
    {
        return $this->maxDepth;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function getRequestDelay(): int
    {
        return $this->requestDelay;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Check if a URL should be crawled based on excludes and custom closure.
     */
    public function isAllowed(string $url): bool
    {
        foreach ($this->excludePatterns as $excludePattern) {
            if (str_contains($url, $excludePattern)) {
                return false;
            }
        }

        if ($this->shouldCrawl instanceof Closure) {
            return ($this->shouldCrawl)($url);
        }

        return true;
    }

    public function maxConcurrent(int $concurrent): self
    {
        $this->maxConcurrent = $concurrent;

        return $this;
    }

    public function maxDepth(int $depth): self
    {
        $this->maxDepth = $depth;

        return $this;
    }

    /**
     * Set the number of retries for failed page loads.
     */
    public function maxRetries(int $retries): self
    {
        $this->maxRetries = $retries;

        return $this;
    }

    /**
     * Fire the onCrawled callback if set.
     */
    public function notifyCrawled(string $url, CrawlResult $crawlResult): void
    {
        if ($this->onCrawled instanceof Closure) {
            ($this->onCrawled)($url, $crawlResult);
        }
    }

    /**
     * Set a callback to fire after each URL is crawled.
     *
     * @param Closure(string, CrawlResult): void $callback
     */
    public function onCrawled(Closure $callback): self
    {
        $this->onCrawled = $callback;

        return $this;
    }

    /**
     * Set a delay in milliseconds between each request.
     * Helps avoid overwhelming the target server.
     */
    public function requestDelay(int $milliseconds): self
    {
        $this->requestDelay = $milliseconds;

        return $this;
    }

    /**
     * Set a closure to evaluate whether a URL should be crawled.
     * Receives the URL string, should return bool.
     *
     * @param Closure(string): bool $callback
     */
    public function shouldCrawl(Closure $callback): self
    {
        $this->shouldCrawl = $callback;

        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }
}
