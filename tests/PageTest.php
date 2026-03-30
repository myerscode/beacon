<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests;

use Myerscode\Beacon\ClientInterface;
use Myerscode\Beacon\CrawlerInterface;
use Myerscode\Beacon\Page;
use PHPUnit\Framework\TestCase;

final class PageTest extends TestCase
{
    public function testAttributeReturnsNullWhenMissing(): void
    {
        $elementCrawler = $this->createCrawlerStub();
        $elementCrawler->method('attr')->willReturn(null);

        $filterCrawler = $this->createCrawlerStub();
        $filterCrawler->method('first')->willReturn($elementCrawler);

        $crawler = $this->createCrawlerStub();
        $crawler->method('filter')->willReturn($filterCrawler);

        $client = $this->createClientStub();
        $client->method('getCrawler')->willReturn($crawler);

        $page = new Page($client, 'https://example.com');

        $this->assertNull($page->attribute('div', 'data-id'));
    }

    public function testAttributeReturnsValue(): void
    {
        $elementCrawler = $this->createCrawlerStub();
        $elementCrawler->method('attr')->willReturn('/about');

        $filterCrawler = $this->createCrawlerStub();
        $filterCrawler->method('first')->willReturn($elementCrawler);

        $crawler = $this->createCrawlerStub();
        $crawler->method('filter')->willReturn($filterCrawler);

        $client = $this->createClientStub();
        $client->method('getCrawler')->willReturn($crawler);

        $page = new Page($client, 'https://example.com');

        $this->assertSame('/about', $page->attribute('a.link', 'href'));
    }

    public function testBodyReturnsBodyHtml(): void
    {
        $bodyCrawler = $this->createCrawlerStub();
        $bodyCrawler->method('html')->willReturn('<p>Hello</p>');

        $crawler = $this->createCrawlerStub();
        $crawler->method('filter')->willReturn($bodyCrawler);

        $client = $this->createClientStub();
        $client->method('getCrawler')->willReturn($crawler);

        $page = new Page($client, 'https://example.com');

        $this->assertSame('<p>Hello</p>', $page->body());
    }

    public function testCrawlerReturnsUnderlyingCrawler(): void
    {
        $crawler = $this->createCrawlerStub();

        $client = $this->createClientStub();
        $client->method('getCrawler')->willReturn($crawler);

        $page = new Page($client, 'https://example.com');

        $this->assertSame($crawler, $page->crawler());
    }

    public function testCurrentUrlReturnsUrl(): void
    {
        $client = $this->createClientStub();
        $client->method('getCurrentURL')->willReturn('https://example.com/redirected');

        $page = new Page($client, 'https://example.com');

        $this->assertSame('https://example.com/redirected', $page->currentUrl());
    }

    public function testHasReturnsFalseWhenElementMissing(): void
    {
        $filterCrawler = $this->createCrawlerStub();
        $filterCrawler->method('count')->willReturn(0);

        $crawler = $this->createCrawlerStub();
        $crawler->method('filter')->willReturn($filterCrawler);

        $client = $this->createClientStub();
        $client->method('getCrawler')->willReturn($crawler);

        $page = new Page($client, 'https://example.com');

        $this->assertFalse($page->has('.missing'));
    }

    public function testHasReturnsTrueWhenElementExists(): void
    {
        $filterCrawler = $this->createCrawlerStub();
        $filterCrawler->method('count')->willReturn(1);

        $crawler = $this->createCrawlerStub();
        $crawler->method('filter')->willReturn($filterCrawler);

        $client = $this->createClientStub();
        $client->method('getCrawler')->willReturn($crawler);

        $page = new Page($client, 'https://example.com');

        $this->assertTrue($page->has('.exists'));
    }

    public function testScreenshotReturnsSelf(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('takeScreenshot')
            ->with('/tmp/test.png');

        $page = new Page($client, 'https://example.com');

        $this->assertSame($page, $page->screenshot('/tmp/test.png'));
    }

    public function testSourceReturnsHtml(): void
    {
        $client = $this->createClientStub();
        $client->method('getPageSource')->willReturn('<html><body>Hello</body></html>');

        $page = new Page($client, 'https://example.com');

        $this->assertSame('<html><body>Hello</body></html>', $page->source());
    }

    public function testTextDefaultsToBody(): void
    {
        $elementCrawler = $this->createCrawlerStub();
        $elementCrawler->method('text')->willReturn('Body text');

        $filterCrawler = $this->createCrawlerStub();
        $filterCrawler->method('first')->willReturn($elementCrawler);

        $crawler = $this->createCrawlerStub();
        $crawler->method('filter')->willReturn($filterCrawler);

        $client = $this->createClientStub();
        $client->method('getCrawler')->willReturn($crawler);

        $page = new Page($client, 'https://example.com');

        $this->assertSame('Body text', $page->text());
    }

    public function testTextReturnsElementText(): void
    {
        $elementCrawler = $this->createCrawlerStub();
        $elementCrawler->method('text')->willReturn('Hello World');

        $filterCrawler = $this->createCrawlerStub();
        $filterCrawler->method('first')->willReturn($elementCrawler);

        $crawler = $this->createCrawlerStub();
        $crawler->method('filter')->willReturn($filterCrawler);

        $client = $this->createClientStub();
        $client->method('getCrawler')->willReturn($crawler);

        $page = new Page($client, 'https://example.com');

        $this->assertSame('Hello World', $page->text('h1'));
    }

    public function testTitleReturnsPageTitle(): void
    {
        $client = $this->createClientStub();
        $client->method('getTitle')->willReturn('Example Page');

        $page = new Page($client, 'https://example.com');

        $this->assertSame('Example Page', $page->title());
    }

    public function testUrlReturnsOriginalUrl(): void
    {
        $client = $this->createClientStub();

        $page = new Page($client, 'https://example.com');

        $this->assertSame('https://example.com', $page->url());
    }

    public function testLinksReturnsAllLinks(): void
    {
        $client = $this->createClientStub();
        $client->method('getPageSource')->willReturn(
            '<html><body><a href="/about">About</a><a href="/contact">Contact</a></body></html>',
        );

        $page = new Page($client, 'https://example.com');

        $this->assertSame(['/about', '/contact'], $page->links());
    }

    public function testLinksDeduplicates(): void
    {
        $client = $this->createClientStub();
        $client->method('getPageSource')->willReturn(
            '<html><body><a href="/about">About</a><a href="/about">About Again</a></body></html>',
        );

        $page = new Page($client, 'https://example.com');

        $this->assertSame(['/about'], $page->links());
    }

    public function testLinksSkipsFragmentsAndJavascript(): void
    {
        $client = $this->createClientStub();
        $client->method('getPageSource')->willReturn(
            '<html><body><a href="#top">Top</a><a href="javascript:void(0)">JS</a><a href="/real">Real</a></body></html>',
        );

        $page = new Page($client, 'https://example.com');

        $this->assertSame(['/real'], $page->links());
    }

    public function testLinksReturnsEmptyForNoLinks(): void
    {
        $client = $this->createClientStub();
        $client->method('getPageSource')->willReturn('<html><body><p>No links</p></body></html>');

        $page = new Page($client, 'https://example.com');

        $this->assertSame([], $page->links());
    }

    public function testMetaReturnsMetaTags(): void
    {
        $client = $this->createClientStub();
        $client->method('getPageSource')->willReturn(
            '<html><head><meta name="description" content="A test page"><meta name="keywords" content="test,page"></head><body></body></html>',
        );

        $page = new Page($client, 'https://example.com');
        $meta = $page->meta();

        $this->assertSame('A test page', $meta['description']);
        $this->assertSame('test,page', $meta['keywords']);
    }

    public function testMetaReturnsOpenGraphTags(): void
    {
        $client = $this->createClientStub();
        $client->method('getPageSource')->willReturn(
            '<html><head><meta property="og:title" content="OG Title"><meta property="og:type" content="website"></head><body></body></html>',
        );

        $page = new Page($client, 'https://example.com');
        $meta = $page->meta();

        $this->assertSame('OG Title', $meta['og:title']);
        $this->assertSame('website', $meta['og:type']);
    }

    public function testMetaReturnsEmptyForNoMeta(): void
    {
        $client = $this->createClientStub();
        $client->method('getPageSource')->willReturn('<html><head></head><body></body></html>');

        $page = new Page($client, 'https://example.com');

        $this->assertSame([], $page->meta());
    }
    private function createClientStub(): ClientInterface
    {
        return $this->createStub(ClientInterface::class);
    }

    private function createCrawlerStub(): CrawlerInterface
    {
        return $this->createStub(CrawlerInterface::class);
    }
}
