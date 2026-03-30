<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests;

use Myerscode\Beacon\ClientInterface;
use Myerscode\Beacon\CrawlerInterface;
use Myerscode\Beacon\Page;
use PHPUnit\Framework\TestCase;

final class PageTest extends TestCase
{
    private function createMockClient(): ClientInterface
    {
        return $this->createMock(ClientInterface::class);
    }

    private function createMockCrawler(): CrawlerInterface
    {
        return $this->createMock(CrawlerInterface::class);
    }

    public function testSourceReturnsHtml(): void
    {
        $client = $this->createMockClient();
        $client->method('getPageSource')->willReturn('<html><body>Hello</body></html>');

        $page = new Page($client, 'https://example.com');

        $this->assertSame('<html><body>Hello</body></html>', $page->source());
    }

    public function testBodyReturnsBodyHtml(): void
    {
        $bodyCrawler = $this->createMockCrawler();
        $bodyCrawler->method('html')->willReturn('<p>Hello</p>');

        $crawler = $this->createMockCrawler();
        $crawler->method('filter')->with('body')->willReturn($bodyCrawler);

        $client = $this->createMockClient();
        $client->method('getCrawler')->willReturn($crawler);

        $page = new Page($client, 'https://example.com');

        $this->assertSame('<p>Hello</p>', $page->body());
    }

    public function testTitleReturnsPageTitle(): void
    {
        $client = $this->createMockClient();
        $client->method('getTitle')->willReturn('Example Page');

        $page = new Page($client, 'https://example.com');

        $this->assertSame('Example Page', $page->title());
    }

    public function testCurrentUrlReturnsUrl(): void
    {
        $client = $this->createMockClient();
        $client->method('getCurrentURL')->willReturn('https://example.com/redirected');

        $page = new Page($client, 'https://example.com');

        $this->assertSame('https://example.com/redirected', $page->currentUrl());
    }

    public function testScreenshotReturnsSelf(): void
    {
        $client = $this->createMockClient();
        $client->expects($this->once())
            ->method('takeScreenshot')
            ->with('/tmp/test.png');

        $page = new Page($client, 'https://example.com');

        $this->assertSame($page, $page->screenshot('/tmp/test.png'));
    }

    public function testTextReturnsElementText(): void
    {
        $elementCrawler = $this->createMockCrawler();
        $elementCrawler->method('text')->willReturn('Hello World');

        $filterCrawler = $this->createMockCrawler();
        $filterCrawler->method('first')->willReturn($elementCrawler);

        $crawler = $this->createMockCrawler();
        $crawler->method('filter')->with('h1')->willReturn($filterCrawler);

        $client = $this->createMockClient();
        $client->method('getCrawler')->willReturn($crawler);

        $page = new Page($client, 'https://example.com');

        $this->assertSame('Hello World', $page->text('h1'));
    }

    public function testTextDefaultsToBody(): void
    {
        $elementCrawler = $this->createMockCrawler();
        $elementCrawler->method('text')->willReturn('Body text');

        $filterCrawler = $this->createMockCrawler();
        $filterCrawler->method('first')->willReturn($elementCrawler);

        $crawler = $this->createMockCrawler();
        $crawler->method('filter')->with('body')->willReturn($filterCrawler);

        $client = $this->createMockClient();
        $client->method('getCrawler')->willReturn($crawler);

        $page = new Page($client, 'https://example.com');

        $this->assertSame('Body text', $page->text());
    }

    public function testAttributeReturnsValue(): void
    {
        $elementCrawler = $this->createMockCrawler();
        $elementCrawler->method('attr')->with('href')->willReturn('/about');

        $filterCrawler = $this->createMockCrawler();
        $filterCrawler->method('first')->willReturn($elementCrawler);

        $crawler = $this->createMockCrawler();
        $crawler->method('filter')->with('a.link')->willReturn($filterCrawler);

        $client = $this->createMockClient();
        $client->method('getCrawler')->willReturn($crawler);

        $page = new Page($client, 'https://example.com');

        $this->assertSame('/about', $page->attribute('a.link', 'href'));
    }

    public function testAttributeReturnsNullWhenMissing(): void
    {
        $elementCrawler = $this->createMockCrawler();
        $elementCrawler->method('attr')->with('data-id')->willReturn(null);

        $filterCrawler = $this->createMockCrawler();
        $filterCrawler->method('first')->willReturn($elementCrawler);

        $crawler = $this->createMockCrawler();
        $crawler->method('filter')->with('div')->willReturn($filterCrawler);

        $client = $this->createMockClient();
        $client->method('getCrawler')->willReturn($crawler);

        $page = new Page($client, 'https://example.com');

        $this->assertNull($page->attribute('div', 'data-id'));
    }

    public function testHasReturnsTrueWhenElementExists(): void
    {
        $filterCrawler = $this->createMockCrawler();
        $filterCrawler->method('count')->willReturn(1);

        $crawler = $this->createMockCrawler();
        $crawler->method('filter')->with('.exists')->willReturn($filterCrawler);

        $client = $this->createMockClient();
        $client->method('getCrawler')->willReturn($crawler);

        $page = new Page($client, 'https://example.com');

        $this->assertTrue($page->has('.exists'));
    }

    public function testHasReturnsFalseWhenElementMissing(): void
    {
        $filterCrawler = $this->createMockCrawler();
        $filterCrawler->method('count')->willReturn(0);

        $crawler = $this->createMockCrawler();
        $crawler->method('filter')->with('.missing')->willReturn($filterCrawler);

        $client = $this->createMockClient();
        $client->method('getCrawler')->willReturn($crawler);

        $page = new Page($client, 'https://example.com');

        $this->assertFalse($page->has('.missing'));
    }

    public function testCrawlerReturnsUnderlyingCrawler(): void
    {
        $crawler = $this->createMockCrawler();

        $client = $this->createMockClient();
        $client->method('getCrawler')->willReturn($crawler);

        $page = new Page($client, 'https://example.com');

        $this->assertSame($crawler, $page->crawler());
    }

    public function testUrlReturnsOriginalUrl(): void
    {
        $client = $this->createMockClient();

        $page = new Page($client, 'https://example.com');

        $this->assertSame('https://example.com', $page->url());
    }
}
