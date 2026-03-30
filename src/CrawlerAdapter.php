<?php

declare(strict_types=1);

namespace Myerscode\Beacon;

use Symfony\Component\Panther\DomCrawler\Crawler;

class CrawlerAdapter implements CrawlerInterface
{
    public function __construct(private readonly Crawler $crawler)
    {
    }

    public function attr(string $attribute): ?string
    {
        return $this->crawler->attr($attribute);
    }

    public function count(): int
    {
        return $this->crawler->count();
    }

    public function filter(string $selector): CrawlerInterface
    {
        return new self($this->crawler->filter($selector));
    }

    public function first(): CrawlerInterface
    {
        return new self($this->crawler->first());
    }

    public function html(): string
    {
        return $this->crawler->html();
    }

    public function text(?string $default = null): string
    {
        return $this->crawler->text($default);
    }
}
