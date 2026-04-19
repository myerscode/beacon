<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Integration;

use Myerscode\Beacon\Browser;
use Myerscode\Beacon\Page;
use PHPUnit\Framework\Attributes\Group;

/**
 * @group integration
 */
#[Group('integration')]
final class VisitAllIntegrationTest extends IntegrationTestCase
{
    private static ?Browser $browser = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$browser = Browser::create()->waitTimeout(10);
    }

    public static function tearDownAfterClass(): void
    {
        self::$browser?->quit();
        self::$browser = null;
        parent::tearDownAfterClass();
    }

    public function testVisitAllReturnsPageForEachUrl(): void
    {
        $urls = [
            self::baseUrl() . '/index.html',
            self::baseUrl() . '/about.html',
            self::baseUrl() . '/contact.html',
        ];

        $pages = self::$browser->visitAll($urls);

        $this->assertCount(3, $pages);

        foreach ($pages as $page) {
            $this->assertInstanceOf(Page::class, $page);
        }
    }

    public function testVisitAllPreservesUrlOrder(): void
    {
        $urls = [
            self::baseUrl() . '/contact.html',
            self::baseUrl() . '/index.html',
            self::baseUrl() . '/about.html',
        ];

        $pages = self::$browser->visitAll($urls);

        $this->assertSame($urls[0], $pages[0]->url());
        $this->assertSame($urls[1], $pages[1]->url());
        $this->assertSame($urls[2], $pages[2]->url());
    }

    public function testVisitAllPagesHaveCorrectTitles(): void
    {
        $urls = [
            self::baseUrl() . '/index.html',
            self::baseUrl() . '/about.html',
            self::baseUrl() . '/contact.html',
        ];

        $pages = self::$browser->visitAll($urls);

        $this->assertSame('Test Homepage', $pages[0]->title());
        $this->assertSame('About Us', $pages[1]->title());
        $this->assertSame('Contact', $pages[2]->title());
    }

    public function testVisitAllPagesHaveRenderedContent(): void
    {
        $urls = [
            self::baseUrl() . '/index.html',
            self::baseUrl() . '/about.html',
        ];

        $pages = self::$browser->visitAll($urls);

        $this->assertStringContainsString('Welcome', $pages[0]->source());
        $this->assertStringContainsString('About Us', $pages[1]->source());
    }

    public function testVisitAllWithSingleUrl(): void
    {
        $pages = self::$browser->visitAll([self::baseUrl() . '/index.html']);

        $this->assertCount(1, $pages);
        $this->assertSame('Test Homepage', $pages[0]->title());
    }

    public function testVisitAllWithConcurrencyOfOne(): void
    {
        $urls = [
            self::baseUrl() . '/index.html',
            self::baseUrl() . '/about.html',
            self::baseUrl() . '/contact.html',
        ];

        $pages = self::$browser->visitAll($urls, concurrency: 1);

        $this->assertCount(3, $pages);
        $this->assertSame('Test Homepage', $pages[0]->title());
        $this->assertSame('About Us', $pages[1]->title());
        $this->assertSame('Contact', $pages[2]->title());
    }

    public function testVisitAllReturnsEmptyForEmptyInput(): void
    {
        $pages = self::$browser->visitAll([]);

        $this->assertSame([], $pages);
    }
}
