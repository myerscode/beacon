<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Crawler;

use Myerscode\Beacon\ChromeDriverManager;
use Myerscode\Beacon\Crawler\CrawlConfig;
use Myerscode\Beacon\Crawler\CrawlResult;
use Myerscode\Beacon\Crawler\CrawlResultCollection;
use Myerscode\Beacon\Crawler\Spider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Exposes protected Spider methods for direct testing.
 */
class TestableSpider extends Spider
{
    public function setBase(string $scheme, string $host): void
    {
        $ref = new ReflectionClass(Spider::class);
        $ref->getProperty('baseScheme')->setValue($this, $scheme);
        $ref->getProperty('baseHost')->setValue($this, $host);
        $ref->getProperty('baseUrl')->setValue($this, "{$scheme}://{$host}");
    }

    public function getCollection(): CrawlResultCollection
    {
        $ref = new ReflectionClass(Spider::class);

        return $ref->getProperty('crawlResultCollection')->getValue($this);
    }

    public function callResolveUrl(string $href, string $currentPage): string
    {
        return $this->resolveUrl($href, $currentPage);
    }

    public function callNormalizeUrl(string $url): string
    {
        return $this->normalizeUrl($url);
    }

    public function callIsInternal(string $url): bool
    {
        return $this->isInternal($url);
    }

    public function callGetDirectoryPath(string $url): string
    {
        return $this->getDirectoryPath($url);
    }

    /**
     * @return array<int, array{url: string}>
     */
    public function callExtractLinks(string $html, string $pageUrl): array
    {
        return $this->extractLinksFromHtml($html, $pageUrl);
    }

    public function callEnqueueLinks(array $newLinks, array $item, array &$queue, array &$queued): void
    {
        $this->enqueueLinks($newLinks, $item, $queue, $queued);
    }
}

final class SpiderTest extends TestCase
{
    private TestableSpider $spider;

    protected function setUp(): void
    {
        $this->spider = new TestableSpider(new CrawlConfig(), $this->createStub(ChromeDriverManager::class));
        $this->spider->setBase('https', 'example.com');
    }

    // =========================================================================
    // resolveUrl
    // =========================================================================

    public function testResolveAbsoluteUrl(): void
    {
        $this->assertSame(
            'https://example.com/about',
            $this->spider->callResolveUrl('https://example.com/about', 'https://example.com/'),
        );
    }

    public function testResolveRootRelativeUrl(): void
    {
        $this->assertSame(
            'https://example.com/about',
            $this->spider->callResolveUrl('/about', 'https://example.com/page'),
        );
    }

    public function testResolveRelativeUrl(): void
    {
        $this->assertSame(
            'https://example.com/blog/post',
            $this->spider->callResolveUrl('post', 'https://example.com/blog/index'),
        );
    }

    public function testResolveProtocolRelativeUrl(): void
    {
        $this->assertSame(
            'https://cdn.example.com/file.js',
            $this->spider->callResolveUrl('//cdn.example.com/file.js', 'https://example.com/'),
        );
    }

    public function testResolveStripsFragment(): void
    {
        $this->assertSame(
            'https://example.com/page',
            $this->spider->callResolveUrl('https://example.com/page#section', 'https://example.com/'),
        );
    }

    public function testResolveIgnoresEmpty(): void
    {
        $this->assertSame('', $this->spider->callResolveUrl('', 'https://example.com/'));
    }

    public function testResolveIgnoresHashOnly(): void
    {
        $this->assertSame('', $this->spider->callResolveUrl('#section', 'https://example.com/'));
    }

    public function testResolveIgnoresJavascript(): void
    {
        $this->assertSame('', $this->spider->callResolveUrl('javascript:void(0)', 'https://example.com/'));
    }

    public function testResolveIgnoresMailto(): void
    {
        $this->assertSame('', $this->spider->callResolveUrl('mailto:test@example.com', 'https://example.com/'));
    }

    public function testResolveIgnoresTel(): void
    {
        $this->assertSame('', $this->spider->callResolveUrl('tel:+1234567890', 'https://example.com/'));
    }

    public function testResolveTrimsWhitespace(): void
    {
        $this->assertSame(
            'https://example.com/about',
            $this->spider->callResolveUrl('  /about  ', 'https://example.com/'),
        );
    }

    // =========================================================================
    // normalizeUrl
    // =========================================================================

    public function testNormalizeUrlStripsFragment(): void
    {
        $this->assertSame(
            'https://example.com/page',
            $this->spider->callNormalizeUrl('https://example.com/page#section'),
        );
    }

    public function testNormalizeUrlStripsTrailingSlash(): void
    {
        $this->assertSame(
            'https://example.com/page',
            $this->spider->callNormalizeUrl('https://example.com/page/'),
        );
    }

    public function testNormalizeUrlHandlesFragmentAfterTrailingSlash(): void
    {
        // rtrim strips trailing / first, but URL ends with #top so / isn't trailing
        // stripFragment then removes #top, leaving the /
        $this->assertSame(
            'https://example.com/page/',
            $this->spider->callNormalizeUrl('https://example.com/page/#top'),
        );
    }

    public function testNormalizeUrlReturnsEmptyForEmpty(): void
    {
        $this->assertSame('', $this->spider->callNormalizeUrl(''));
    }

    // =========================================================================
    // isInternal
    // =========================================================================

    public function testIsInternalSameDomain(): void
    {
        $this->assertTrue($this->spider->callIsInternal('https://example.com/page'));
    }

    public function testIsInternalDifferentDomain(): void
    {
        $this->assertFalse($this->spider->callIsInternal('https://other.com/page'));
    }

    public function testIsInternalSubdomainIsFalse(): void
    {
        $this->assertFalse($this->spider->callIsInternal('https://sub.example.com/page'));
    }

    // =========================================================================
    // getDirectoryPath
    // =========================================================================

    public function testGetDirectoryPathFromFileUrl(): void
    {
        $this->assertSame('/blog', $this->spider->callGetDirectoryPath('https://example.com/blog/post'));
    }

    public function testGetDirectoryPathFromRootUrl(): void
    {
        $this->assertSame('/', $this->spider->callGetDirectoryPath('https://example.com/'));
    }

    public function testGetDirectoryPathFromUrlWithNoPath(): void
    {
        $this->assertSame('/', $this->spider->callGetDirectoryPath('https://example.com'));
    }

    public function testGetDirectoryPathFromDeepUrl(): void
    {
        $this->assertSame('/a/b/c', $this->spider->callGetDirectoryPath('https://example.com/a/b/c/page.html'));
    }

    // =========================================================================
    // extractLinksFromHtml
    // =========================================================================

    public function testExtractsAbsoluteLinks(): void
    {
        $links = $this->spider->callExtractLinks(
            '<html><body><a href="https://example.com/about">About</a></body></html>',
            'https://example.com',
        );

        $this->assertCount(1, $links);
        $this->assertSame('https://example.com/about', $links[0]['url']);
    }

    public function testExtractsMultipleLinks(): void
    {
        $links = $this->spider->callExtractLinks(
            '<html><body><a href="/one">One</a><a href="/two">Two</a><a href="/three">Three</a></body></html>',
            'https://example.com',
        );

        $this->assertCount(3, $links);
    }

    public function testExtractsRelativeLinks(): void
    {
        $links = $this->spider->callExtractLinks(
            '<html><body><a href="/contact">Contact</a></body></html>',
            'https://example.com/page',
        );

        $this->assertCount(1, $links);
        $this->assertSame('https://example.com/contact', $links[0]['url']);
    }

    public function testExtractsExternalLinks(): void
    {
        $links = $this->spider->callExtractLinks(
            '<html><body><a href="https://other.com/page">External</a></body></html>',
            'https://example.com',
        );

        $this->assertCount(1, $links);
        $this->assertSame('https://other.com/page', $links[0]['url']);
    }

    public function testExtractsLinksWithQueryParameters(): void
    {
        $links = $this->spider->callExtractLinks(
            '<html><body><a href="/search?q=test&page=2">Search</a></body></html>',
            'https://example.com',
        );

        $this->assertCount(1, $links);
        $this->assertStringContainsString('/search?q=test', $links[0]['url']);
    }

    public function testExtractsProtocolRelativeLinks(): void
    {
        $links = $this->spider->callExtractLinks(
            '<html><body><a href="//cdn.example.com/file">CDN</a></body></html>',
            'https://example.com',
        );

        $this->assertCount(1, $links);
        $this->assertSame('https://cdn.example.com/file', $links[0]['url']);
    }

    public function testExtractsSingleQuotedHrefs(): void
    {
        $links = $this->spider->callExtractLinks(
            "<html><body><a href='/about'>About</a></body></html>",
            'https://example.com',
        );

        $this->assertCount(1, $links);
        $this->assertSame('https://example.com/about', $links[0]['url']);
    }

    public function testExtractReturnsEmptyForEmptyHtml(): void
    {
        $this->assertSame([], $this->spider->callExtractLinks('', 'https://example.com'));
    }

    public function testExtractReturnsEmptyForNoLinks(): void
    {
        $this->assertSame(
            [],
            $this->spider->callExtractLinks('<html><body><p>No links</p></body></html>', 'https://example.com'),
        );
    }

    public function testExtractSkipsFragmentOnlyLinks(): void
    {
        $this->assertSame(
            [],
            $this->spider->callExtractLinks('<html><body><a href="#section">Jump</a></body></html>', 'https://example.com'),
        );
    }

    public function testExtractSkipsJavascriptLinks(): void
    {
        $this->assertSame(
            [],
            $this->spider->callExtractLinks('<html><body><a href="javascript:void(0)">JS</a></body></html>', 'https://example.com'),
        );
    }

    public function testExtractSkipsMailtoLinks(): void
    {
        $links = $this->spider->callExtractLinks(
            '<html><body><a href="mailto:test@example.com">Email</a><a href="/real">Real</a></body></html>',
            'https://example.com',
        );

        $this->assertCount(1, $links);
        $this->assertSame('https://example.com/real', $links[0]['url']);
    }

    public function testExtractSkipsTelLinks(): void
    {
        $this->assertSame(
            [],
            $this->spider->callExtractLinks('<html><body><a href="tel:+44123456">Call</a></body></html>', 'https://example.com'),
        );
    }

    public function testExtractStripsFragmentsFromLinks(): void
    {
        $links = $this->spider->callExtractLinks(
            '<html><body><a href="/page#section">Page</a></body></html>',
            'https://example.com',
        );

        $this->assertCount(1, $links);
        $this->assertSame('https://example.com/page', $links[0]['url']);
    }

    // =========================================================================
    // enqueueLinks
    // =========================================================================

    public function testEnqueueLinksAddsInternalLinkToQueue(): void
    {
        $queue  = [];
        $queued = ['https://example.com' => true];

        $this->spider->callEnqueueLinks(
            [['url' => 'https://example.com/about']],
            ['url' => 'https://example.com', 'depth' => 0, 'source' => ''],
            $queue,
            $queued,
        );

        $this->assertCount(1, $queue);
        $this->assertSame('https://example.com/about', $queue[0]['url']);
        $this->assertSame(1, $queue[0]['depth']);
        $this->assertTrue($queued['https://example.com/about']);
    }

    public function testEnqueueLinksRecordsExternalLinkWithoutQueuing(): void
    {
        $queue  = [];
        $queued = ['https://example.com' => true];

        $this->spider->callEnqueueLinks(
            [['url' => 'https://other.com/page']],
            ['url' => 'https://example.com', 'depth' => 0, 'source' => ''],
            $queue,
            $queued,
        );

        $this->assertSame([], $queue);

        $external = $this->spider->getCollection()->get('https://other.com/page');
        $this->assertNotNull($external);
        $this->assertFalse($external->internal);
        $this->assertNull($external->statusCode);
    }

    public function testEnqueueLinksSkipsDuplicateInternalLinks(): void
    {
        $queue  = [];
        $queued = [
            'https://example.com'       => true,
            'https://example.com/about' => true,
        ];

        $this->spider->getCollection()->add(
            new CrawlResult('https://example.com/about', true, 200, ['https://example.com'], 1),
        );

        $this->spider->callEnqueueLinks(
            [['url' => 'https://example.com/about']],
            ['url' => 'https://example.com/contact', 'depth' => 1, 'source' => 'https://example.com'],
            $queue,
            $queued,
        );

        $this->assertSame([], $queue);

        $result = $this->spider->getCollection()->get('https://example.com/about');
        $this->assertContains('https://example.com/contact', $result->linkedFrom);
    }

    public function testEnqueueLinksRespectsMaxDepth(): void
    {
        $config = (new CrawlConfig())->maxDepth(1);
        $spider = new TestableSpider($config, $this->createStub(ChromeDriverManager::class));
        $spider->setBase('https', 'example.com');

        $queue  = [];
        $queued = ['https://example.com' => true];

        $spider->callEnqueueLinks(
            [['url' => 'https://example.com/deep']],
            ['url' => 'https://example.com/page', 'depth' => 1, 'source' => 'https://example.com'],
            $queue,
            $queued,
        );

        $this->assertSame([], $queue);
    }

    public function testEnqueueLinksRespectsExcludePatterns(): void
    {
        $config = (new CrawlConfig())->exclude(['/admin']);
        $spider = new TestableSpider($config, $this->createStub(ChromeDriverManager::class));
        $spider->setBase('https', 'example.com');

        $queue  = [];
        $queued = ['https://example.com' => true];

        $spider->callEnqueueLinks(
            [['url' => 'https://example.com/admin/dashboard']],
            ['url' => 'https://example.com', 'depth' => 0, 'source' => ''],
            $queue,
            $queued,
        );

        $this->assertSame([], $queue);
    }

    public function testEnqueueLinksSkipsEmptyNormalizedUrls(): void
    {
        $queue  = [];
        $queued = ['https://example.com' => true];

        $this->spider->callEnqueueLinks(
            [['url' => '']],
            ['url' => 'https://example.com', 'depth' => 0, 'source' => ''],
            $queue,
            $queued,
        );

        $this->assertSame([], $queue);
    }

    // =========================================================================
    // crawl — seed page behaviour
    // =========================================================================

    public function testCrawlRecordsStartPage(): void
    {
        $spider  = new Spider(new CrawlConfig(), $this->createStub(ChromeDriverManager::class));
        $results = $spider->crawl('https://example.com', '<html><body>No links</body></html>');

        $this->assertCount(1, $results);
        $this->assertTrue($results->has('https://example.com'));

        $start = $results->get('https://example.com');
        $this->assertNotNull($start);
        $this->assertTrue($start->internal);
        $this->assertSame(200, $start->statusCode);
        $this->assertSame(0, $start->depth);
    }

    public function testCrawlNormalizesTrailingSlash(): void
    {
        $spider  = new Spider(new CrawlConfig(), $this->createStub(ChromeDriverManager::class));
        $results = $spider->crawl('https://example.com/', '<html><body></body></html>');

        $this->assertTrue($results->has('https://example.com'));
    }

    public function testCrawlRecordsExternalLinksWithoutFollowing(): void
    {
        $spider  = new Spider(new CrawlConfig(), $this->createStub(ChromeDriverManager::class));
        $results = $spider->crawl(
            'https://example.com',
            '<html><body><a href="https://other.com/page">External</a></body></html>',
        );

        $external = $results->get('https://other.com/page');
        $this->assertNotNull($external);
        $this->assertFalse($external->internal);
        $this->assertNull($external->statusCode);
        $this->assertContains('https://example.com', $external->linkedFrom);
    }

    public function testCrawlDeduplicatesSeedLinks(): void
    {
        $spider  = new Spider(new CrawlConfig(), $this->createStub(ChromeDriverManager::class));
        $results = $spider->crawl(
            'https://example.com',
            '<html><body><a href="https://other.com">Link 1</a><a href="https://other.com">Link 2</a></body></html>',
        );

        $this->assertNotNull($results->get('https://other.com'));
    }

    public function testCrawlRespectsExcludePatterns(): void
    {
        $spider  = new Spider((new CrawlConfig())->exclude(['/admin']), $this->createStub(ChromeDriverManager::class));
        $results = $spider->crawl(
            'https://example.com',
            '<html><body><a href="/admin/dashboard">Admin</a><a href="https://other.com">External</a></body></html>',
        );

        $this->assertFalse($results->has('https://example.com/admin/dashboard'));
        $this->assertTrue($results->has('https://other.com'));
    }

    public function testCrawlRespectsMaxDepthZero(): void
    {
        $spider  = new Spider((new CrawlConfig())->maxDepth(0), $this->createStub(ChromeDriverManager::class));
        $results = $spider->crawl('https://example.com', '<html><body><a href="/about">About</a></body></html>');

        $this->assertCount(1, $results);
        $this->assertTrue($results->has('https://example.com'));
    }

    public function testCrawlRespectsShouldCrawlClosure(): void
    {
        $config  = (new CrawlConfig())->shouldCrawl(fn (string $url) => !str_contains($url, 'private'));
        $spider  = new Spider($config, $this->createStub(ChromeDriverManager::class));
        $results = $spider->crawl(
            'https://example.com',
            '<html><body><a href="/private/page">Private</a></body></html>',
        );

        $this->assertFalse($results->has('https://example.com/private/page'));
    }

    public function testCrawlFiresOnCrawledForStartPage(): void
    {
        $crawled = [];
        $config  = (new CrawlConfig())->onCrawled(function (string $url) use (&$crawled): void {
            $crawled[] = $url;
        });

        $spider = new Spider($config, $this->createStub(ChromeDriverManager::class));
        $spider->crawl('https://example.com', '<html><body></body></html>');

        $this->assertContains('https://example.com', $crawled);
    }

    public function testCrawlFiresOnCrawledForExternalLinks(): void
    {
        $crawled = [];
        $config  = (new CrawlConfig())->onCrawled(function (string $url) use (&$crawled): void {
            $crawled[] = $url;
        });

        $spider = new Spider($config, $this->createStub(ChromeDriverManager::class));
        $spider->crawl('https://example.com', '<html><body><a href="https://other.com">External</a></body></html>');

        $this->assertContains('https://other.com', $crawled);
    }

    public function testCrawlSetsBaseFromStartUrl(): void
    {
        $spider = new TestableSpider(new CrawlConfig(), $this->createStub(ChromeDriverManager::class));
        $spider->crawl('http://example.com', '<html><body></body></html>');

        $ref = new ReflectionClass(Spider::class);
        $this->assertSame('http', $ref->getProperty('baseScheme')->getValue($spider));
        $this->assertSame('example.com', $ref->getProperty('baseHost')->getValue($spider));
    }

    public function testCrawlSkipsDuplicateInternalSeedLinks(): void
    {
        $crawled = [];
        $config  = (new CrawlConfig())->maxDepth(0)->onCrawled(function (string $url) use (&$crawled): void {
            $crawled[] = $url;
        });

        $spider  = new Spider($config, $this->createStub(ChromeDriverManager::class));
        $results = $spider->crawl(
            'https://example.com',
            '<html><body><a href="/about">About</a><a href="/about">About Again</a></body></html>',
        );

        $this->assertCount(1, $results);
        $this->assertSame(['https://example.com'], $crawled);
    }

    public function testCrawlRecordsMultipleExternalLinksFromSamePage(): void
    {
        $spider  = new Spider(new CrawlConfig(), $this->createStub(ChromeDriverManager::class));
        $results = $spider->crawl(
            'https://example.com',
            '<html><body><a href="https://a.com">A</a><a href="https://b.com">B</a><a href="https://c.com">C</a></body></html>',
        );

        $this->assertTrue($results->has('https://a.com'));
        $this->assertTrue($results->has('https://b.com'));
        $this->assertTrue($results->has('https://c.com'));
        $this->assertCount(4, $results);
    }

    public function testCrawlExternalLinkDepthIsOne(): void
    {
        $spider  = new Spider(new CrawlConfig(), $this->createStub(ChromeDriverManager::class));
        $results = $spider->crawl(
            'https://example.com',
            '<html><body><a href="https://other.com">External</a></body></html>',
        );

        $external = $results->get('https://other.com');
        $this->assertNotNull($external);
        $this->assertSame(1, $external->depth);
    }

    public function testCrawlMixedInternalAndExternalLinks(): void
    {
        $spider  = new Spider((new CrawlConfig())->maxDepth(0), $this->createStub(ChromeDriverManager::class));
        $results = $spider->crawl(
            'https://example.com',
            '<html><body><a href="/about">About</a><a href="https://other.com">Other</a></body></html>',
        );

        $this->assertFalse($results->has('https://example.com/about'));
        $this->assertTrue($results->has('https://other.com'));
    }
}
