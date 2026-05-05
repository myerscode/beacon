<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Crawler;

class CrawlResult
{
    /**
     * @param string   $url        The discovered URL
     * @param bool     $internal   Whether the URL is on the same domain
     * @param int|null $statusCode HTTP status code (null if not fetched, e.g. external or asset)
     * @param string[] $linkedFrom URLs of pages that link to this URL
     * @param int      $depth      The depth at which this URL was discovered
     * @param bool     $asset      Whether this is a static asset (CSS, JS, image, font) rather than a page
     */
    public function __construct(
        public readonly string $url,
        public readonly bool $internal,
        public readonly ?int $statusCode,
        public readonly array $linkedFrom,
        public readonly int $depth,
        public readonly bool $asset = false,
    ) {
    }

    /**
     * Create a new result with an additional source URL.
     */
    public function withLinkedFrom(string $sourceUrl): self
    {
        return new self(
            $this->url,
            $this->internal,
            $this->statusCode,
            array_unique([...$this->linkedFrom, $sourceUrl]),
            $this->depth,
            $this->asset,
        );
    }

    /**
     * Create a new result with a status code.
     */
    public function withStatusCode(int $statusCode): self
    {
        return new self(
            $this->url,
            $this->internal,
            $statusCode,
            $this->linkedFrom,
            $this->depth,
            $this->asset,
        );
    }
}
