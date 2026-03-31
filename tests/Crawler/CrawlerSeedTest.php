<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Crawler;

use Myerscode\Beacon\ChromeDriverManager;
use Myerscode\Beacon\Crawler\Spider;
use Myerscode\Beacon\Crawler\CrawlConfig;
use PHPUnit\Framework\TestCase;

final class CrawlerSeedTest extends TestCase
{
    public function testCrawlDeduplicatesSeedLinks(): void
    {
        $driver  = $this->createStub(ChromeDriverManager::class);
        $crawler = new Spider(new CrawlConfig(), $driver);

        $html = '<html><body><a href="https://other.com">Link 1</a><a href="https://other.com">Link 2</a></body></html>';
        $results = $crawler->crawl('https://example.com', $html);

        // External link should appear once
        $external = $results->get('https://other.com');
        $this->assertNotNull($external);
    }

    public function testCrawlFiresOnCrawledForExternalLinks(): void
    {
        $crawled = [];
        $config  = (new CrawlConfig())->onCrawled(function (string $url) use (&$crawled): void {
            $crawled[] = $url;
        });

        $driver  = $this->createStub(ChromeDriverManager::class);
        $crawler = new Spider($config, $driver);

        $html = '<html><body><a href="https://other.com">External</a></body></html>';
        $crawler->crawl('https://example.com', $html);

        $this->assertContains('https://other.com', $crawled);
    }

    public function testCrawlFiresOnCrawledForStartPage(): void
    {
        $crawled = [];
        $config  = (new CrawlConfig())->onCrawled(function (string $url) use (&$crawled): void {
            $crawled[] = $url;
        });

        $driver  = $this->createStub(ChromeDriverManager::class);
        $crawler = new Spider($config, $driver);

        $crawler->crawl('https://example.com', '<html><body></body></html>');

        $this->assertContains('https://example.com', $crawled);
    }

    public function testCrawlNormalizesTrailingSlash(): void
    {
        $driver  = $this->createStub(ChromeDriverManager::class);
        $crawler = new Spider(new CrawlConfig(), $driver);

        $results = $crawler->crawl('https://example.com/', '<html><body></body></html>');

        $this->assertTrue($results->has('https://example.com'));
    }

    public function testCrawlRecordsExternalLinksWithoutFollowing(): void
    {
        $driver  = $this->createStub(ChromeDriverManager::class);
        $crawler = new Spider(new CrawlConfig(), $driver);

        $html = '<html><body><a href="https://other.com/page">External</a></body></html>';
        $results = $crawler->crawl('https://example.com', $html);

        $this->assertTrue($results->has('https://other.com/page'));

        $external = $results->get('https://other.com/page');
        $this->assertNotNull($external);
        $this->assertFalse($external->internal);
        $this->assertNull($external->statusCode);
        $this->assertContains('https://example.com', $external->linkedFrom);
    }
    public function testCrawlRecordsStartPage(): void
    {
        $driver  = $this->createStub(ChromeDriverManager::class);
        $crawler = new Spider(new CrawlConfig(), $driver);

        // HTML with no links — crawl should just record the start page
        $results = $crawler->crawl('https://example.com', '<html><body>No links</body></html>');

        $this->assertCount(1, $results);
        $this->assertTrue($results->has('https://example.com'));

        $start = $results->get('https://example.com');
        $this->assertNotNull($start);
        $this->assertTrue($start->internal);
        $this->assertSame(200, $start->statusCode);
        $this->assertSame(0, $start->depth);
    }

    public function testCrawlRespectsExcludePatterns(): void
    {
        $config  = (new CrawlConfig())->exclude(['/admin']);
        $driver  = $this->createStub(ChromeDriverManager::class);
        $crawler = new Spider($config, $driver);

        $html = '<html><body><a href="/admin/dashboard">Admin</a><a href="https://other.com">External</a></body></html>';
        $results = $crawler->crawl('https://example.com', $html);

        // Admin link should be excluded, external should be recorded
        $this->assertFalse($results->has('https://example.com/admin/dashboard'));
        $this->assertTrue($results->has('https://other.com'));
    }

    public function testCrawlRespectsMaxDepthZero(): void
    {
        $config  = (new CrawlConfig())->maxDepth(0);
        $driver  = $this->createStub(ChromeDriverManager::class);
        $crawler = new Spider($config, $driver);

        $html = '<html><body><a href="/about">About</a></body></html>';
        $results = $crawler->crawl('https://example.com', $html);

        // Only the start page, no links followed
        $this->assertCount(1, $results);
        $this->assertTrue($results->has('https://example.com'));
    }

    public function testCrawlRespectsShouldCrawlClosure(): void
    {
        $config = (new CrawlConfig())->shouldCrawl(
            fn (string $url) => !str_contains($url, 'private'),
        );
        $driver  = $this->createStub(ChromeDriverManager::class);
        $crawler = new Spider($config, $driver);

        $html = '<html><body><a href="/private/page">Private</a></body></html>';
        $results = $crawler->crawl('https://example.com', $html);

        $this->assertFalse($results->has('https://example.com/private/page'));
    }
}
