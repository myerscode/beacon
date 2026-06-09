<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests;

use Myerscode\Beacon\Client\ClientInterface;
use Myerscode\Beacon\Lighthouse\Category;
use Myerscode\Beacon\Lighthouse\LighthouseResult;
use Myerscode\Beacon\Lighthouse\LighthouseRunner;
use Myerscode\Beacon\Page;
use PHPUnit\Framework\TestCase;

final class PageLighthouseTest extends TestCase
{
    public function testLighthouseDelegatesToRunner(): void
    {
        $runner = $this->createMock(LighthouseRunner::class);
        $runner->expects($this->once())
            ->method('run')
            ->with('https://example.com')
            ->willReturn(new LighthouseResult($this->sampleData()));

        $page = new Page($this->createStub(ClientInterface::class), 'https://example.com');
        $page->withLighthouseRunner($runner);

        $scores = $page->lighthouse();

        $this->assertArrayHasKey('performance', $scores);
    }

    public function testLighthouseCachesResult(): void
    {
        $runner = $this->createMock(LighthouseRunner::class);
        $runner->expects($this->once())
            ->method('run')
            ->willReturn(new LighthouseResult($this->sampleData()));

        $page = new Page($this->createStub(ClientInterface::class), 'https://example.com');
        $page->withLighthouseRunner($runner);

        $page->lighthouse();
        $page->lighthouse();
    }

    public function testLighthousePassesCategoryFilters(): void
    {
        $page = $this->createPageWithMockedLighthouse();

        $all    = $page->lighthouse();
        $single = $page->lighthouse(Category::Performance);

        $this->assertCount(4, $all);
        $this->assertCount(1, $single);
    }

    public function testAuditDelegatesToLighthouseResult(): void
    {
        $page   = $this->createPageWithMockedLighthouse();
        $audits = $page->audit();

        $this->assertCount(2, $audits);
    }

    public function testLighthouseResultReturnsFullObject(): void
    {
        $page            = $this->createPageWithMockedLighthouse();
        $lighthouseResult = $page->lighthouseResult();

        $this->assertInstanceOf(LighthouseResult::class, $lighthouseResult);
        $this->assertSame($this->sampleData(), $lighthouseResult->raw());
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
