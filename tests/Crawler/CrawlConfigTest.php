<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Crawler;

use Myerscode\Beacon\Crawler\CrawlConfig;
use Myerscode\Beacon\Crawler\CrawlResult;
use PHPUnit\Framework\TestCase;

final class CrawlConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $crawlConfig = new CrawlConfig();

        $this->assertSame(5, $crawlConfig->getMaxDepth());
        $this->assertSame(5, $crawlConfig->getMaxConcurrent());
        $this->assertSame(30, $crawlConfig->getTimeout());
        $this->assertSame([], $crawlConfig->getExcludePatterns());
    }

    public function testExcludePatterns(): void
    {
        $crawlConfig = new CrawlConfig()->exclude(['/admin', '/login']);

        $this->assertSame(['/admin', '/login'], $crawlConfig->getExcludePatterns());
    }

    public function testFluentChaining(): void
    {
        $crawlConfig = new CrawlConfig()
            ->maxDepth(3)
            ->maxConcurrent(2)
            ->timeout(15)
            ->exclude(['/admin'])
            ->shouldCrawl(fn (string $url): true => true)
            ->onCrawled(fn (string $url, \Myerscode\Beacon\Crawler\CrawlResult $crawlResult): null => null);

        $this->assertSame(3, $crawlConfig->getMaxDepth());
        $this->assertSame(2, $crawlConfig->getMaxConcurrent());
        $this->assertSame(15, $crawlConfig->getTimeout());
        $this->assertSame(['/admin'], $crawlConfig->getExcludePatterns());
    }

    public function testIsAllowedBlocksExcludedPatterns(): void
    {
        $crawlConfig = new CrawlConfig()->exclude(['/admin', '/login']);

        $this->assertFalse($crawlConfig->isAllowed('https://example.com/admin/dashboard'));
        $this->assertFalse($crawlConfig->isAllowed('https://example.com/login'));
        $this->assertTrue($crawlConfig->isAllowed('https://example.com/about'));
    }

    public function testIsAllowedExcludesTakesPrecedenceOverClosure(): void
    {
        $crawlConfig = new CrawlConfig()
            ->exclude(['/blocked'])
            ->shouldCrawl(fn (string $url): true => true);

        $this->assertFalse($crawlConfig->isAllowed('https://example.com/blocked'));
    }

    public function testIsAllowedWithClosure(): void
    {
        $crawlConfig = new CrawlConfig()->shouldCrawl(
            fn (string $url): bool => !str_contains($url, 'private'),
        );

        $this->assertFalse($crawlConfig->isAllowed('https://example.com/private/page'));
        $this->assertTrue($crawlConfig->isAllowed('https://example.com/public/page'));
    }

    public function testIsAllowedWithNoFilters(): void
    {
        $crawlConfig = new CrawlConfig();

        $this->assertTrue($crawlConfig->isAllowed('https://example.com/page'));
    }

    public function testMaxConcurrent(): void
    {
        $crawlConfig = new CrawlConfig()->maxConcurrent(3);

        $this->assertSame(3, $crawlConfig->getMaxConcurrent());
    }

    public function testMaxDepth(): void
    {
        $crawlConfig = new CrawlConfig()->maxDepth(10);

        $this->assertSame(10, $crawlConfig->getMaxDepth());
    }

    public function testNotifyCrawledDoesNothingWithoutCallback(): void
    {
        $crawlConfig = new CrawlConfig();
        $crawlResult = new CrawlResult('https://example.com', true, 200, [], 0);

        // Should not throw
        $crawlConfig->notifyCrawled('https://example.com', $crawlResult);

        $this->assertTrue(true);
    }

    public function testOnCrawledCallbackIsFired(): void
    {
        $called = [];

        $crawlConfig = (new CrawlConfig())->onCrawled(function (string $url, CrawlResult $crawlResult) use (&$called): void {
            $called[] = $url;
        });

        $crawlResult = new CrawlResult('https://example.com', true, 200, [], 0);
        $crawlConfig->notifyCrawled('https://example.com', $crawlResult);

        $this->assertSame(['https://example.com'], $called);
    }

    public function testTimeout(): void
    {
        $crawlConfig = new CrawlConfig()->timeout(60);

        $this->assertSame(60, $crawlConfig->getTimeout());
    }
}
