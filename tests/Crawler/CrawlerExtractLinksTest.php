<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Crawler;

use Myerscode\Beacon\ChromeDriverManager;
use Myerscode\Beacon\Crawler\Spider;
use Myerscode\Beacon\Crawler\CrawlConfig;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionClass;

final class CrawlerExtractLinksTest extends TestCase
{
    private Spider $crawler;

    protected function setUp(): void
    {
        $this->crawler = new Spider(new CrawlConfig(), $this->createStub(ChromeDriverManager::class));

        $ref = new ReflectionClass($this->crawler);
        $ref->getProperty('baseScheme')->setValue($this->crawler, 'https');
        $ref->getProperty('baseHost')->setValue($this->crawler, 'example.com');
        $ref->getProperty('baseUrl')->setValue($this->crawler, 'https://example.com');
    }

    public function testExtractsAbsoluteLinks(): void
    {
        $html = '<html><body><a href="https://example.com/about">About</a></body></html>';

        $links = $this->extractLinks($html, 'https://example.com');

        $this->assertCount(1, $links);
        $this->assertSame('https://example.com/about', $links[0]['url']);
    }

    public function testExtractsMultipleLinks(): void
    {
        $html = '<html><body><a href="/one">One</a><a href="/two">Two</a><a href="/three">Three</a></body></html>';

        $links = $this->extractLinks($html, 'https://example.com');

        $this->assertCount(3, $links);
    }

    public function testExtractsRelativeLinks(): void
    {
        $html = '<html><body><a href="/contact">Contact</a></body></html>';

        $links = $this->extractLinks($html, 'https://example.com/page');

        $this->assertCount(1, $links);
        $this->assertSame('https://example.com/contact', $links[0]['url']);
    }

    public function testHandlesExternalLinks(): void
    {
        $html = '<html><body><a href="https://other.com/page">External</a></body></html>';

        $links = $this->extractLinks($html, 'https://example.com');

        $this->assertCount(1, $links);
        $this->assertSame('https://other.com/page', $links[0]['url']);
    }

    public function testHandlesLinksWithQueryParameters(): void
    {
        $html = '<html><body><a href="/search?q=test&page=2">Search</a></body></html>';

        $links = $this->extractLinks($html, 'https://example.com');

        $this->assertCount(1, $links);
        $this->assertStringContainsString('/search?q=test', $links[0]['url']);
    }

    public function testHandlesProtocolRelativeLinks(): void
    {
        $html = '<html><body><a href="//cdn.example.com/file">CDN</a></body></html>';

        $links = $this->extractLinks($html, 'https://example.com');

        $this->assertCount(1, $links);
        $this->assertSame('https://cdn.example.com/file', $links[0]['url']);
    }

    public function testHandlesSingleQuotedHrefs(): void
    {
        $html = "<html><body><a href='/about'>About</a></body></html>";

        $links = $this->extractLinks($html, 'https://example.com');

        $this->assertCount(1, $links);
        $this->assertSame('https://example.com/about', $links[0]['url']);
    }

    public function testReturnsEmptyForEmptyHtml(): void
    {
        $links = $this->extractLinks('', 'https://example.com');

        $this->assertSame([], $links);
    }

    public function testReturnsEmptyForNoLinks(): void
    {
        $html = '<html><body><p>No links here</p></body></html>';

        $links = $this->extractLinks($html, 'https://example.com');

        $this->assertSame([], $links);
    }

    public function testSkipsFragmentOnlyLinks(): void
    {
        $html = '<html><body><a href="#section">Jump</a></body></html>';

        $links = $this->extractLinks($html, 'https://example.com');

        $this->assertSame([], $links);
    }

    public function testSkipsJavascriptLinks(): void
    {
        $html = '<html><body><a href="javascript:void(0)">JS</a></body></html>';

        $links = $this->extractLinks($html, 'https://example.com');

        $this->assertSame([], $links);
    }

    public function testSkipsMailtoLinks(): void
    {
        $html = '<html><body><a href="mailto:test@example.com">Email</a><a href="/real">Real</a></body></html>';

        $links = $this->extractLinks($html, 'https://example.com');

        $this->assertCount(1, $links);
        $this->assertSame('https://example.com/real', $links[0]['url']);
    }

    public function testSkipsTelLinks(): void
    {
        $html = '<html><body><a href="tel:+44123456">Call</a></body></html>';

        $links = $this->extractLinks($html, 'https://example.com');

        $this->assertSame([], $links);
    }

    public function testStripsFragmentsFromLinks(): void
    {
        $html = '<html><body><a href="/page#section">Page</a></body></html>';

        $links = $this->extractLinks($html, 'https://example.com');

        $this->assertCount(1, $links);
        $this->assertSame('https://example.com/page', $links[0]['url']);
    }

    /**
     * @return array<int, array{url: string}>
     */
    private function extractLinks(string $html, string $pageUrl): array
    {
        $method = new ReflectionMethod($this->crawler, 'extractLinksFromHtml');

        return $method->invoke($this->crawler, $html, $pageUrl);
    }
}
