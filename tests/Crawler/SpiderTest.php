<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Crawler;

use Myerscode\Beacon\Client\ClientFactory;
use Myerscode\Beacon\Client\ClientInterface;
use Myerscode\Beacon\Crawler\CrawlConfig;
use Myerscode\Beacon\Crawler\Spider;
use PHPUnit\Framework\TestCase;

final class SpiderTest extends TestCase
{
    // =========================================================================
    // Start page behaviour
    // =========================================================================

    public function testCrawlRecordsStartPage(): void
    {
        $results = $this->crawl('https://example.com', '<html><body>No links</body></html>');

        $this->assertCount(1, $results);
        $this->assertTrue($results->has('https://example.com'));

        $start = $results->get('https://example.com');
        $this->assertTrue($start->internal);
        $this->assertSame(200, $start->statusCode);
        $this->assertSame(0, $start->depth);
    }

    public function testCrawlNormalizesTrailingSlashOnStartUrl(): void
    {
        $results = $this->crawl('https://example.com/', '<html><body></body></html>');

        $this->assertTrue($results->has('https://example.com'));
        $this->assertFalse($results->has('https://example.com/'));
    }

    // =========================================================================
    // Link extraction and resolution
    // =========================================================================

    public function testCrawlExtractsAbsoluteLinks(): void
    {
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="https://example.com/about">About</a></body></html>',
        );

        $this->assertTrue($results->has('https://example.com/about'));
    }

    public function testCrawlExtractsRootRelativeLinks(): void
    {
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="/contact">Contact</a></body></html>',
        );

        $this->assertTrue($results->has('https://example.com/contact'));
    }

    public function testCrawlExtractsPathRelativeLinks(): void
    {
        $results = $this->crawl(
            'https://example.com/blog/index',
            '<html><body><a href="post">Post</a></body></html>',
        );

        $this->assertTrue($results->has('https://example.com/blog/post'));
    }

    public function testCrawlResolvesProtocolRelativeLinks(): void
    {
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="//cdn.example.com/file">CDN</a></body></html>',
        );

        $this->assertTrue($results->has('https://cdn.example.com/file'));
    }

    public function testCrawlExtractsLinksWithQueryParameters(): void
    {
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="/search?q=test&page=2">Search</a></body></html>',
        );

        $result = $results->get('https://example.com/search?q=test&page=2');
        $this->assertNotNull($result);
    }

    public function testCrawlExtractsSingleQuotedHrefs(): void
    {
        $results = $this->crawl(
            'https://example.com',
            "<html><body><a href='/about'>About</a></body></html>",
        );

        $this->assertTrue($results->has('https://example.com/about'));
    }

    public function testCrawlStripsFragmentsFromLinks(): void
    {
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="/page#section">Page</a></body></html>',
        );

        $this->assertTrue($results->has('https://example.com/page'));
        $this->assertFalse($results->has('https://example.com/page#section'));
    }

    public function testCrawlTrimsWhitespaceFromHrefs(): void
    {
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="  /about  ">About</a></body></html>',
        );

        $this->assertTrue($results->has('https://example.com/about'));
    }

    // =========================================================================
    // Skipped link types
    // =========================================================================

    public function testCrawlSkipsFragmentOnlyLinks(): void
    {
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="#section">Jump</a></body></html>',
        );

        $this->assertCount(1, $results); // Only the start page
    }

    public function testCrawlSkipsJavascriptLinks(): void
    {
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="javascript:void(0)">JS</a></body></html>',
        );

        $this->assertCount(1, $results);
    }

    public function testCrawlSkipsMailtoLinks(): void
    {
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="mailto:test@example.com">Email</a><a href="/real">Real</a></body></html>',
        );

        $this->assertFalse($results->has('mailto:test@example.com'));
        $this->assertTrue($results->has('https://example.com/real'));
    }

    public function testCrawlSkipsTelLinks(): void
    {
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="tel:+44123456">Call</a></body></html>',
        );

        $this->assertCount(1, $results);
    }

    // =========================================================================
    // Internal vs external classification
    // =========================================================================

    public function testCrawlRecordsExternalLinksWithoutFollowing(): void
    {
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="https://other.com/page">External</a></body></html>',
        );

        $external = $results->get('https://other.com/page');
        $this->assertNotNull($external);
        $this->assertFalse($external->internal);
        $this->assertNull($external->statusCode);
        $this->assertContains('https://example.com', $external->linkedFrom);
    }

    public function testCrawlTreatsSubdomainAsExternal(): void
    {
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="https://sub.example.com/page">Sub</a></body></html>',
        );

        $sub = $results->get('https://sub.example.com/page');
        $this->assertNotNull($sub);
        $this->assertFalse($sub->internal);
    }

    public function testCrawlExternalLinkDepthIsOne(): void
    {
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="https://other.com">External</a></body></html>',
        );

        $external = $results->get('https://other.com');
        $this->assertSame(1, $external->depth);
    }

    public function testCrawlRecordsMultipleExternalLinks(): void
    {
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="https://a.com">A</a><a href="https://b.com">B</a><a href="https://c.com">C</a></body></html>',
        );

        $this->assertTrue($results->has('https://a.com'));
        $this->assertTrue($results->has('https://b.com'));
        $this->assertTrue($results->has('https://c.com'));
        $this->assertCount(4, $results); // start + 3 external
    }

    public function testCrawlMixedInternalAndExternalWithMaxDepthZero(): void
    {
        $config  = (new CrawlConfig())->maxDepth(0);
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="/about">About</a><a href="https://other.com">Other</a></body></html>',
            $config,
        );

        // Internal links not followed at depth 0, but externals still recorded
        $this->assertFalse($results->has('https://example.com/about'));
        $this->assertTrue($results->has('https://other.com'));
    }

    // =========================================================================
    // Deduplication
    // =========================================================================

    public function testCrawlDeduplicatesExternalLinks(): void
    {
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="https://other.com">Link 1</a><a href="https://other.com">Link 2</a></body></html>',
        );

        $this->assertCount(2, $results); // start + 1 deduplicated external
    }

    public function testCrawlDeduplicatesInternalSeedLinks(): void
    {
        $crawled = [];
        $config  = (new CrawlConfig())->maxDepth(0)->onCrawled(function (string $url) use (&$crawled): void {
            $crawled[] = $url;
        });

        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="/about">About</a><a href="/about">About Again</a></body></html>',
            $config,
        );

        $this->assertCount(1, $results);
        $this->assertSame(['https://example.com'], $crawled);
    }

    // =========================================================================
    // Configuration — maxDepth
    // =========================================================================

    public function testCrawlRespectsMaxDepthZero(): void
    {
        $config  = (new CrawlConfig())->maxDepth(0);
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="/about">About</a></body></html>',
            $config,
        );

        $this->assertCount(1, $results);
        $this->assertTrue($results->has('https://example.com'));
    }

    // =========================================================================
    // Configuration — exclude patterns
    // =========================================================================

    public function testCrawlRespectsExcludePatterns(): void
    {
        $config  = (new CrawlConfig())->exclude(['/admin']);
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="/admin/dashboard">Admin</a><a href="https://other.com">External</a></body></html>',
            $config,
        );

        $this->assertFalse($results->has('https://example.com/admin/dashboard'));
        $this->assertTrue($results->has('https://other.com'));
    }

    // =========================================================================
    // Configuration — shouldCrawl closure
    // =========================================================================

    public function testCrawlRespectsShouldCrawlClosure(): void
    {
        $config  = (new CrawlConfig())->shouldCrawl(fn (string $url) => !str_contains($url, 'private'));
        $results = $this->crawl(
            'https://example.com',
            '<html><body><a href="/private/page">Private</a></body></html>',
            $config,
        );

        $this->assertFalse($results->has('https://example.com/private/page'));
    }

    // =========================================================================
    // Callbacks
    // =========================================================================

    public function testCrawlFiresOnCrawledForStartPage(): void
    {
        $crawled = [];
        $config  = (new CrawlConfig())->onCrawled(function (string $url) use (&$crawled): void {
            $crawled[] = $url;
        });

        $this->crawl('https://example.com', '<html><body></body></html>', $config);

        $this->assertContains('https://example.com', $crawled);
    }

    public function testCrawlFiresOnCrawledForExternalLinks(): void
    {
        $crawled = [];
        $config  = (new CrawlConfig())->onCrawled(function (string $url) use (&$crawled): void {
            $crawled[] = $url;
        });

        $this->crawl(
            'https://example.com',
            '<html><body><a href="https://other.com">External</a></body></html>',
            $config,
        );

        $this->assertContains('https://other.com', $crawled);
    }

    // =========================================================================
    // Multi-page crawl (mocked client)
    // =========================================================================

    public function testCrawlFollowsInternalLinksAndRecordsLinkedFrom(): void
    {
        $client = $this->createStub(ClientInterface::class);

        $client->method('getPageSource')->willReturn(
            '<html><body><a href="https://example.com/from-about">Deep Link</a></body></html>',
        );
        $client->method('getStatusCode')->willReturn(200);

        $factory = $this->createStub(ClientFactory::class);
        $factory->method('create')->willReturn($client);

        $config = (new CrawlConfig())->maxDepth(2);
        $spider = new Spider($config, $factory);

        $results = $spider->crawl(
            'https://example.com',
            '<html><body><a href="/about">About</a></body></html>',
        );

        // Start page + /about (followed) + /from-about (discovered from /about)
        $this->assertTrue($results->has('https://example.com'));
        $this->assertTrue($results->has('https://example.com/about'));

        $about = $results->get('https://example.com/about');
        $this->assertSame(200, $about->statusCode);
        $this->assertContains('https://example.com', $about->linkedFrom);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Run a single-page crawl (no internal links followed) with a stub client factory.
     */
    private function crawl(string $startUrl, string $html, ?CrawlConfig $config = null): \Myerscode\Beacon\Crawler\CrawlResultCollection
    {
        $spider = new Spider(
            $config ?? new CrawlConfig(),
            $this->createStub(ClientFactory::class),
        );

        return $spider->crawl($startUrl, $html);
    }
}
