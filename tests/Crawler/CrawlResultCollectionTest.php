<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Crawler;

use Myerscode\Beacon\Crawler\CrawlResult;
use Myerscode\Beacon\Crawler\CrawlResultCollection;
use PHPUnit\Framework\TestCase;

final class CrawlResultCollectionTest extends TestCase
{
    public function testAddAndGet(): void
    {
        $crawlResultCollection = new CrawlResultCollection();
        $crawlResult     = new CrawlResult('https://example.com', true, 200, [], 0);

        $crawlResultCollection->add($crawlResult);

        $this->assertTrue($crawlResultCollection->has('https://example.com'));
        $this->assertSame($crawlResult->url, $crawlResultCollection->get('https://example.com')?->url);
    }

    public function testAddMergesLinkedFrom(): void
    {
        $crawlResultCollection = new CrawlResultCollection();

        $crawlResultCollection->add(new CrawlResult('https://example.com/about', true, 200, ['/home'], 1));
        $crawlResultCollection->add(new CrawlResult('https://example.com/about', true, 200, ['/contact'], 2));

        $result = $crawlResultCollection->get('https://example.com/about');

        $this->assertInstanceOf(\Myerscode\Beacon\Crawler\CrawlResult::class, $result);
        $this->assertContains('/home', $result->linkedFrom);
        $this->assertContains('/contact', $result->linkedFrom);
        $this->assertSame(1, $result->depth); // Keeps the lower depth
    }

    public function testAll(): void
    {
        $crawlResultCollection = new CrawlResultCollection();

        $crawlResultCollection->add(new CrawlResult('https://example.com', true, 200, [], 0));
        $crawlResultCollection->add(new CrawlResult('https://other.com', false, null, [], 1));

        $this->assertCount(2, $crawlResultCollection->all());
    }

    public function testBroken(): void
    {
        $crawlResultCollection = new CrawlResultCollection();

        $crawlResultCollection->add(new CrawlResult('https://example.com', true, 200, [], 0));
        $crawlResultCollection->add(new CrawlResult('https://example.com/missing', true, 404, [], 1));
        $crawlResultCollection->add(new CrawlResult('https://example.com/error', true, 500, [], 1));
        $crawlResultCollection->add(new CrawlResult('https://other.com', false, null, [], 1));

        $broken = $crawlResultCollection->broken();

        $this->assertCount(2, $broken);
        $this->assertArrayHasKey('https://example.com/missing', $broken);
        $this->assertArrayHasKey('https://example.com/error', $broken);
    }

    public function testCount(): void
    {
        $crawlResultCollection = new CrawlResultCollection();

        $crawlResultCollection->add(new CrawlResult('https://example.com', true, 200, [], 0));
        $crawlResultCollection->add(new CrawlResult('https://example.com/about', true, 200, [], 1));

        $this->assertCount(2, $crawlResultCollection);
    }

    public function testExternal(): void
    {
        $crawlResultCollection = new CrawlResultCollection();

        $crawlResultCollection->add(new CrawlResult('https://example.com', true, 200, [], 0));
        $crawlResultCollection->add(new CrawlResult('https://other.com', false, null, [], 1));

        $external = $crawlResultCollection->external();

        $this->assertCount(1, $external);
        $this->assertArrayHasKey('https://other.com', $external);
    }

    public function testGetReturnsNullForMissing(): void
    {
        $crawlResultCollection = new CrawlResultCollection();

        $this->assertNotInstanceOf(\Myerscode\Beacon\Crawler\CrawlResult::class, $crawlResultCollection->get('https://missing.com'));
    }

    public function testHasReturnsFalseForMissing(): void
    {
        $crawlResultCollection = new CrawlResultCollection();

        $this->assertFalse($crawlResultCollection->has('https://missing.com'));
    }

    public function testInternal(): void
    {
        $crawlResultCollection = new CrawlResultCollection();

        $crawlResultCollection->add(new CrawlResult('https://example.com', true, 200, [], 0));
        $crawlResultCollection->add(new CrawlResult('https://other.com', false, null, [], 1));

        $internal = $crawlResultCollection->internal();

        $this->assertCount(1, $internal);
        $this->assertArrayHasKey('https://example.com', $internal);
    }

    public function testIterable(): void
    {
        $crawlResultCollection = new CrawlResultCollection();

        $crawlResultCollection->add(new CrawlResult('https://example.com', true, 200, [], 0));
        $crawlResultCollection->add(new CrawlResult('https://example.com/about', true, 200, [], 1));

        $urls = [];
        foreach ($crawlResultCollection as $url => $result) {
            $urls[] = $url;
        }

        $this->assertCount(2, $urls);
        $this->assertContains('https://example.com', $urls);
        $this->assertContains('https://example.com/about', $urls);
    }

    public function testWithStatus(): void
    {
        $crawlResultCollection = new CrawlResultCollection();

        $crawlResultCollection->add(new CrawlResult('https://example.com', true, 200, [], 0));
        $crawlResultCollection->add(new CrawlResult('https://example.com/missing', true, 404, [], 1));
        $crawlResultCollection->add(new CrawlResult('https://example.com/error', true, 500, [], 1));

        $this->assertCount(1, $crawlResultCollection->withStatus(200));
        $this->assertCount(1, $crawlResultCollection->withStatus(404));
    }
}
