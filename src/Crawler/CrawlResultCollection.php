<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Crawler;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<string, CrawlResult>
 */
class CrawlResultCollection implements Countable, IteratorAggregate
{
    /**
     * @var array<string, CrawlResult>
     */
    private array $results = [];

    public function add(CrawlResult $crawlResult): void
    {
        $url = $crawlResult->url;

        if (isset($this->results[$url])) {
            $existing = $this->results[$url];

            // Merge linkedFrom sources
            $merged = array_unique([...$existing->linkedFrom, ...$crawlResult->linkedFrom]);

            $this->results[$url] = new CrawlResult(
                $url,
                $crawlResult->internal,
                $crawlResult->statusCode ?? $existing->statusCode,
                $merged,
                min($existing->depth, $crawlResult->depth),
            );
        } else {
            $this->results[$url] = $crawlResult;
        }
    }

    /**
     * Get all results.
     *
     * @return array<string, CrawlResult>
     */
    public function all(): array
    {
        return $this->results;
    }

    /**
     * Get results with broken status codes (4xx, 5xx).
     *
     * @return array<string, CrawlResult>
     */
    public function broken(): array
    {
        return array_filter(
            $this->results,
            fn (CrawlResult $crawlResult): bool => $crawlResult->statusCode !== null && $crawlResult->statusCode >= 400,
        );
    }

    public function count(): int
    {
        return count($this->results);
    }

    /**
     * Get only external results.
     *
     * @return array<string, CrawlResult>
     */
    public function external(): array
    {
        return array_filter($this->results, fn (CrawlResult $crawlResult): bool => !$crawlResult->internal);
    }

    public function get(string $url): ?CrawlResult
    {
        return $this->results[$url] ?? null;
    }

    /**
     * @return ArrayIterator<string, CrawlResult>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->results);
    }

    public function has(string $url): bool
    {
        return isset($this->results[$url]);
    }

    /**
     * Get only internal results.
     *
     * @return array<string, CrawlResult>
     */
    public function internal(): array
    {
        return array_filter($this->results, fn (CrawlResult $crawlResult): bool => $crawlResult->internal);
    }

    /**
     * Get results with a specific status code.
     *
     * @return array<string, CrawlResult>
     */
    public function withStatus(int $statusCode): array
    {
        return array_filter($this->results, fn (CrawlResult $crawlResult): bool => $crawlResult->statusCode === $statusCode);
    }

    /**
     * Convert all results to a plain array.
     *
     * @return array<int, array{url: string, internal: bool, statusCode: int|null, linkedFrom: string[], depth: int}>
     */
    public function toArray(): array
    {
        return array_values(array_map(
            fn (CrawlResult $crawlResult): array => [
                'url'        => $crawlResult->url,
                'internal'   => $crawlResult->internal,
                'statusCode' => $crawlResult->statusCode,
                'linkedFrom' => $crawlResult->linkedFrom,
                'depth'      => $crawlResult->depth,
            ],
            $this->results,
        ));
    }

    /**
     * Convert all results to a JSON string.
     */
    public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->toArray(), $flags | JSON_THROW_ON_ERROR);
    }
}
