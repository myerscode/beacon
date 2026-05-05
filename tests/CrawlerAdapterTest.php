<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests;

use Myerscode\Beacon\Client\CrawlerAdapter;
use Myerscode\Beacon\Client\CrawlerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

final class CrawlerAdapterTest extends TestCase
{
    // =========================================================================
    // Integration — realistic page fragment
    // =========================================================================

    public function testAllMethodsWorkTogetherOnRealisticHtml(): void
    {
        $html = <<<'HTML'
            <article class="post" data-id="123">
                <header>
                    <h1 class="post-title">Getting Started with Beacon</h1>
                    <time datetime="2025-03-15">March 15, 2025</time>
                </header>
                <div class="post-body">
                    <p>Beacon is a fluent PHP wrapper for Chrome automation.</p>
                    <p>It supports screenshots, PDFs, and crawling.</p>
                </div>
                <footer>
                    <a href="/tags/php" class="tag">PHP</a>
                    <a href="/tags/chrome" class="tag">Chrome</a>
                    <a href="/tags/automation" class="tag">Automation</a>
                </footer>
            </article>
        HTML;

        $adapter = $this->createAdapterFromHtml($html);

        // attr — reads attribute values
        $this->assertSame('123', $adapter->filter('article')->attr('data-id'));
        $this->assertSame('/tags/php', $adapter->filter('a.tag')->first()->attr('href'));

        // text — extracts text content
        $this->assertSame('Getting Started with Beacon', $adapter->filter('.post-title')->text());
        $this->assertSame('March 15, 2025', $adapter->filter('time')->text());
        $this->assertSame('PHP', $adapter->filter('a.tag')->first()->text());

        // count — counts matching elements
        $this->assertSame(3, $adapter->filter('a.tag')->count());
        $this->assertSame(2, $adapter->filter('.post-body p')->count());
        $this->assertSame(1, $adapter->filter('h1')->count());

        // html — returns inner HTML
        $body = $adapter->filter('.post-body')->html();
        $this->assertStringContainsString('fluent PHP wrapper', $body);
        $this->assertStringContainsString('<p>', $body);

        // filter chaining — narrows scope
        $footerLinks = $adapter->filter('footer')->filter('a');
        $this->assertSame(3, $footerLinks->count());
    }
    // =========================================================================
    // Edge cases
    // =========================================================================

    public function testAttrReturnsNullForMissingAttribute(): void
    {
        $adapter = $this->createAdapterFromHtml('<span>No attributes</span>');

        $this->assertNull($adapter->filter('span')->attr('data-missing'));
    }

    public function testCountReturnsZeroWhenNoMatch(): void
    {
        $adapter = $this->createAdapterFromHtml('<p>Just a paragraph</p>');

        $this->assertSame(0, $adapter->filter('h1')->count());
    }

    // =========================================================================
    // Return types
    // =========================================================================

    public function testFilterReturnsCrawlerInterface(): void
    {
        $adapter = $this->createAdapterFromHtml('<div><p>Hello</p></div>');

        $this->assertInstanceOf(CrawlerInterface::class, $adapter->filter('p'));
    }

    public function testFirstReturnsCrawlerInterface(): void
    {
        $adapter = $this->createAdapterFromHtml('<p>One</p><p>Two</p>');

        $this->assertInstanceOf(CrawlerInterface::class, $adapter->filter('p')->first());
    }

    public function testTextReturnsDefaultWhenNoMatch(): void
    {
        $adapter = $this->createAdapterFromHtml('<p>Exists</p>');

        $this->assertSame('N/A', $adapter->filter('h1')->text('N/A'));
    }

    public function testTextStripsNestedTags(): void
    {
        $adapter = $this->createAdapterFromHtml('<p>Hello <em>world</em>!</p>');

        $this->assertSame('Hello world!', $adapter->filter('p')->text());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createAdapterFromHtml(string $html): CrawlerAdapter
    {
        return new CrawlerAdapter(new Crawler($html));
    }
}
