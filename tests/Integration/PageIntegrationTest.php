<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Integration;

use Myerscode\Beacon\Browser;
use PHPUnit\Framework\Attributes\Group;

/**
 * @group integration
 */
#[Group('integration')]
final class PageIntegrationTest extends IntegrationTestCase
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

    public function testBodyReturnsBodyContent(): void
    {
        $page = self::$browser->visit(self::baseUrl() . '/index.html');

        $body = $page->body();

        $this->assertStringContainsString('Welcome', $body);
        $this->assertStringNotContainsString('<html', $body);
    }

    public function testCurrentUrlReturnsActualUrl(): void
    {
        $page = self::$browser->visit(self::baseUrl() . '/index.html');

        $this->assertStringContainsString('/index.html', $page->currentUrl());
    }

    public function testHasFindsElement(): void
    {
        $page = self::$browser->visit(self::baseUrl() . '/index.html');

        $this->assertTrue($page->has('h1'));
        $this->assertFalse($page->has('.nonexistent'));
    }

    public function testLinksReturnsPageLinks(): void
    {
        $page = self::$browser->visit(self::baseUrl() . '/index.html');

        $links = $page->links();

        $this->assertContains('/about.html', $links);
        $this->assertContains('/contact.html', $links);
        $this->assertContains('https://external.example.com', $links);
        // Should not contain mailto or fragment-only links
        $this->assertNotContains('mailto:test@example.com', $links);
        $this->assertNotContains('#top', $links);
    }

    public function testMetaReturnsMetaTags(): void
    {
        $page = self::$browser->visit(self::baseUrl() . '/index.html');

        $meta = $page->meta();

        $this->assertSame('Test fixture homepage', $meta['description']);
        $this->assertSame('Test Site', $meta['og:title']);
    }

    public function testScreenshotCreatesFile(): void
    {
        $path = sys_get_temp_dir() . '/beacon-integration-test-' . uniqid() . '.png';

        $page = self::$browser->visit(self::baseUrl() . '/index.html');
        $page->screenshot($path);

        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));

        unlink($path);
    }

    public function testSourceReturnsRenderedHtml(): void
    {
        $page = self::$browser->visit(self::baseUrl() . '/index.html');

        $source = $page->source();

        $this->assertStringContainsString('<h1>Welcome</h1>', $source);
        $this->assertStringContainsString('Test Homepage', $source);
    }

    public function testStatusCodeReturns200(): void
    {
        $page = self::$browser->visit(self::baseUrl() . '/index.html');

        $this->assertSame(200, $page->statusCode());
    }

    public function testTextReturnsElementContent(): void
    {
        $page = self::$browser->visit(self::baseUrl() . '/index.html');

        $this->assertSame('Welcome', $page->text('h1'));
    }

    public function testTitleReturnsPageTitle(): void
    {
        $page = self::$browser->visit(self::baseUrl() . '/index.html');

        $this->assertSame('Test Homepage', $page->title());
    }

    public function testUrlReturnsOriginalUrl(): void
    {
        $url  = self::baseUrl() . '/index.html';
        $page = self::$browser->visit($url);

        $this->assertSame($url, $page->url());
    }
}
