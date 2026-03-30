<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Crawler;

use Myerscode\Beacon\Crawler\CrawlResult;
use PHPUnit\Framework\TestCase;

final class CrawlResultTest extends TestCase
{
    public function testConstructor(): void
    {
        $crawlResult = new CrawlResult(
            'https://example.com',
            true,
            200,
            ['https://example.com/home'],
            0,
        );

        $this->assertSame('https://example.com', $crawlResult->url);
        $this->assertTrue($crawlResult->internal);
        $this->assertSame(200, $crawlResult->statusCode);
        $this->assertSame(['https://example.com/home'], $crawlResult->linkedFrom);
        $this->assertSame(0, $crawlResult->depth);
    }

    public function testExternalResult(): void
    {
        $crawlResult = new CrawlResult('https://other.com', false, null, ['https://example.com'], 1);

        $this->assertFalse($crawlResult->internal);
        $this->assertNull($crawlResult->statusCode);
    }

    public function testWithLinkedFromAddsSource(): void
    {
        $crawlResult = new CrawlResult('https://example.com/about', true, 200, ['/home'], 1);

        $updated = $crawlResult->withLinkedFrom('/contact');

        $this->assertSame(['/home', '/contact'], $updated->linkedFrom);
        $this->assertSame(['/home'], $crawlResult->linkedFrom); // Original unchanged
    }

    public function testWithLinkedFromDeduplicates(): void
    {
        $crawlResult = new CrawlResult('https://example.com/about', true, 200, ['/home'], 1);

        $updated = $crawlResult->withLinkedFrom('/home');

        $this->assertSame(['/home'], $updated->linkedFrom);
    }

    public function testWithStatusCode(): void
    {
        $crawlResult = new CrawlResult('https://example.com', true, null, [], 0);

        $updated = $crawlResult->withStatusCode(404);

        $this->assertSame(404, $updated->statusCode);
        $this->assertNull($crawlResult->statusCode); // Original unchanged
    }
}
