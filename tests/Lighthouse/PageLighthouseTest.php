<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Lighthouse;

use Myerscode\Beacon\ClientInterface;
use Myerscode\Beacon\Lighthouse\Audit;
use Myerscode\Beacon\Lighthouse\Category;
use Myerscode\Beacon\Lighthouse\LighthouseResult;
use Myerscode\Beacon\Lighthouse\LighthouseRunner;
use Myerscode\Beacon\Page;
use PHPUnit\Framework\TestCase;

final class PageLighthouseTest extends TestCase
{
    public function testAuditFiltersByEnum(): void
    {
        $page   = $this->createPageWithMockedLighthouse();
        $audits = $page->audit(Audit::FirstContentfulPaint);

        $this->assertCount(1, $audits);
        $this->assertArrayHasKey('first-contentful-paint', $audits);
    }

    public function testAuditFiltersByMultiple(): void
    {
        $page   = $this->createPageWithMockedLighthouse();
        $audits = $page->audit(Audit::FirstContentfulPaint, Audit::LargestContentfulPaint);

        $this->assertCount(2, $audits);
    }

    public function testAuditFiltersByString(): void
    {
        $page   = $this->createPageWithMockedLighthouse();
        $audits = $page->audit('first-contentful-paint');

        $this->assertCount(1, $audits);
    }

    public function testAuditReturnsAllWhenNoArgs(): void
    {
        $page   = $this->createPageWithMockedLighthouse();
        $audits = $page->audit();

        $this->assertCount(2, $audits);
        $this->assertArrayHasKey('first-contentful-paint', $audits);
        $this->assertArrayHasKey('largest-contentful-paint', $audits);
    }

    public function testLighthouseFiltersCategories(): void
    {
        $page   = $this->createPageWithMockedLighthouse();
        $scores = $page->lighthouse(Category::Performance);

        $this->assertSame(['performance' => 92], $scores);
    }

    public function testLighthouseResultCachesResult(): void
    {
        $client = $this->createStub(ClientInterface::class);

        $runner = $this->createMock(LighthouseRunner::class);
        $runner->expects($this->once())
            ->method('run')
            ->willReturn(new LighthouseResult($this->sampleData()));

        $page = new Page($client, 'https://example.com');
        $page->withLighthouseRunner($runner);

        // Call twice — runner should only be invoked once
        $page->lighthouse();
        $page->lighthouse();
    }

    public function testLighthouseResultReturnsFullObject(): void
    {
        $page   = $this->createPageWithMockedLighthouse();
        $lighthouseResult = $page->lighthouseResult();

        $this->assertInstanceOf(LighthouseResult::class, $lighthouseResult);
        $this->assertSame($this->sampleData(), $lighthouseResult->raw());
    }

    public function testLighthouseReturnsAllScores(): void
    {
        $page   = $this->createPageWithMockedLighthouse();
        $scores = $page->lighthouse();

        $this->assertSame([
            'performance'    => 92,
            'accessibility'  => 100,
            'best-practices' => 95,
            'seo'            => 90,
        ], $scores);
    }

    private function createPageWithMockedLighthouse(): Page
    {
        $runner = $this->createStub(LighthouseRunner::class);
        $runner->method('run')
            ->willReturn(new LighthouseResult($this->sampleData()));

        $page = new Page($this->createStub(ClientInterface::class), 'https://example.com');
        $page->withLighthouseRunner($runner);

        return $page;
    }
    /**
     * @return array<string, mixed>
     */
    private function sampleData(): array
    {
        return [
            'categories' => [
                'performance'    => ['score' => 0.92],
                'accessibility'  => ['score' => 1.0],
                'best-practices' => ['score' => 0.95],
                'seo'            => ['score' => 0.9],
            ],
            'audits' => [
                'first-contentful-paint' => [
                    'id'    => 'first-contentful-paint',
                    'title' => 'First Contentful Paint',
                    'score' => 0.98,
                ],
                'largest-contentful-paint' => [
                    'id'    => 'largest-contentful-paint',
                    'title' => 'Largest Contentful Paint',
                    'score' => 0.85,
                ],
            ],
        ];
    }
}
