<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Integration;

use Myerscode\Beacon\Browser;
use Myerscode\Beacon\Crawler\CrawlConfig;
use Myerscode\Beacon\Crawler\CrawlResult;
use PHPUnit\Framework\Attributes\Group;

/**
 * @group integration
 */
#[Group('integration')]
final class CrawlIntegrationTest extends IntegrationTestCase
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

    public function testCrawlFindsExternalLinks(): void
    {
        $config = (new CrawlConfig())->maxDepth(1)->maxConcurrent(1);

        $page    = self::$browser->visit(self::baseUrl() . '/index.html');
        $results = $page->crawl($config);

        $external = $results->external();

        $this->assertGreaterThanOrEqual(1, count($external));
    }

    public function testCrawlFindsInternalPages(): void
    {
        $config = (new CrawlConfig())->maxDepth(2)->maxConcurrent(2);

        $page    = self::$browser->visit(self::baseUrl() . '/index.html');
        $results = $page->crawl($config);

        $internal = $results->internal();

        $this->assertGreaterThanOrEqual(3, count($internal));
    }

    public function testCrawlOnCrawledCallbackFires(): void
    {
        $crawled = [];
        $config  = (new CrawlConfig())
            ->maxDepth(1)
            ->maxConcurrent(1)
            ->onCrawled(function (string $url, CrawlResult $crawlResult) use (&$crawled): void {
                $crawled[] = $url;
            });

        $page = self::$browser->visit(self::baseUrl() . '/index.html');
        $page->crawl($config);

        $this->assertNotEmpty($crawled);
    }

    public function testCrawlRespectsMaxDepth(): void
    {
        $config = (new CrawlConfig())->maxDepth(0);

        $page    = self::$browser->visit(self::baseUrl() . '/index.html');
        $results = $page->crawl($config);

        // Only the start page
        $this->assertCount(1, $results->internal());
    }

    public function testCrawlResultsSerialise(): void
    {
        $config = (new CrawlConfig())->maxDepth(1)->maxConcurrent(1);

        $page    = self::$browser->visit(self::baseUrl() . '/index.html');
        $results = $page->crawl($config);

        $array = $results->toArray();
        $json  = $results->toJson();

        $this->assertNotEmpty($array);
        $this->assertIsString($json);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertCount(count($array), $decoded);
    }
}
