<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Lighthouse;

use Myerscode\Beacon\Lighthouse\Audit;
use Myerscode\Beacon\Lighthouse\Category;
use Myerscode\Beacon\Lighthouse\LighthouseResult;
use PHPUnit\Framework\TestCase;

final class LighthouseResultTest extends TestCase
{
    public function testAuditsFiltersByEnum(): void
    {
        $lighthouseResult = new LighthouseResult($this->sampleData());

        $audits = $lighthouseResult->audits(Audit::FirstContentfulPaint);

        $this->assertCount(1, $audits);
        $this->assertArrayHasKey('first-contentful-paint', $audits);
        $this->assertEqualsWithDelta(0.98, $audits['first-contentful-paint']['score'], PHP_FLOAT_EPSILON);
    }

    public function testAuditsFiltersByMultipleEnums(): void
    {
        $lighthouseResult = new LighthouseResult($this->sampleData());

        $audits = $lighthouseResult->audits(Audit::FirstContentfulPaint, Audit::ColorContrast);

        $this->assertCount(2, $audits);
        $this->assertArrayHasKey('first-contentful-paint', $audits);
        $this->assertArrayHasKey('color-contrast', $audits);
    }

    public function testAuditsFiltersByString(): void
    {
        $lighthouseResult = new LighthouseResult($this->sampleData());

        $audits = $lighthouseResult->audits('color-contrast');

        $this->assertCount(1, $audits);
        $this->assertArrayHasKey('color-contrast', $audits);
    }

    public function testAuditsMixEnumAndString(): void
    {
        $lighthouseResult = new LighthouseResult($this->sampleData());

        $audits = $lighthouseResult->audits(Audit::FirstContentfulPaint, 'color-contrast');

        $this->assertCount(2, $audits);
        $this->assertArrayHasKey('first-contentful-paint', $audits);
        $this->assertArrayHasKey('color-contrast', $audits);
    }

    public function testAuditsReturnsAllWhenNoArgs(): void
    {
        $lighthouseResult = new LighthouseResult($this->sampleData());

        $audits = $lighthouseResult->audits();

        $this->assertCount(3, $audits);
        $this->assertArrayHasKey('first-contentful-paint', $audits);
        $this->assertArrayHasKey('largest-contentful-paint', $audits);
        $this->assertArrayHasKey('color-contrast', $audits);
    }

    public function testAuditsReturnsEmptyForUnknown(): void
    {
        $lighthouseResult = new LighthouseResult($this->sampleData());

        $audits = $lighthouseResult->audits('nonexistent-audit');

        $this->assertSame([], $audits);
    }

    public function testAuditsWithEmptyData(): void
    {
        $lighthouseResult = new LighthouseResult([]);

        $this->assertSame([], $lighthouseResult->audits());
    }

    public function testRawReturnsFullData(): void
    {
        $data   = $this->sampleData();
        $lighthouseResult = new LighthouseResult($data);

        $this->assertSame($data, $lighthouseResult->raw());
    }

    public function testSaveJsonWritesFile(): void
    {
        $lighthouseResult = new LighthouseResult($this->sampleData());
        $path   = sys_get_temp_dir() . '/beacon-test-' . uniqid() . '.json';

        $lighthouseResult->saveJson($path);

        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertIsString($contents);

        $decoded = json_decode($contents, true);
        $this->assertEqualsCanonicalizing($this->sampleData(), $decoded);

        unlink($path);
    }

    public function testScoresFiltersByCategory(): void
    {
        $lighthouseResult = new LighthouseResult($this->sampleData());

        $scores = $lighthouseResult->scores(Category::Performance, Category::Seo);

        $this->assertSame([
            'performance' => 92,
            'seo'         => 90,
        ], $scores);
    }

    public function testScoresHandlesNullScore(): void
    {
        $data = ['categories' => ['performance' => ['score' => null]]];
        $lighthouseResult = new LighthouseResult($data);

        $scores = $lighthouseResult->scores();

        $this->assertSame(['performance' => null], $scores);
    }

    public function testScoresReturnsAllCategories(): void
    {
        $lighthouseResult = new LighthouseResult($this->sampleData());

        $scores = $lighthouseResult->scores();

        $this->assertSame([
            'performance'    => 92,
            'accessibility'  => 100,
            'best-practices' => 95,
            'seo'            => 90,
        ], $scores);
    }

    public function testScoresSingleCategory(): void
    {
        $lighthouseResult = new LighthouseResult($this->sampleData());

        $scores = $lighthouseResult->scores(Category::Accessibility);

        $this->assertSame(['accessibility' => 100], $scores);
    }

    public function testScoresWithEmptyCategories(): void
    {
        $lighthouseResult = new LighthouseResult([]);

        $this->assertSame([], $lighthouseResult->scores());
    }
    /**
     * @return array<string, mixed>
     */
    private function sampleData(): array
    {
        return [
            'categories' => [
                'performance' => ['score' => 0.92],
                'accessibility' => ['score' => 1.0],
                'best-practices' => ['score' => 0.95],
                'seo' => ['score' => 0.9],
            ],
            'audits' => [
                'first-contentful-paint' => [
                    'id' => 'first-contentful-paint',
                    'title' => 'First Contentful Paint',
                    'score' => 0.98,
                    'numericValue' => 800,
                    'displayValue' => '0.8 s',
                ],
                'largest-contentful-paint' => [
                    'id' => 'largest-contentful-paint',
                    'title' => 'Largest Contentful Paint',
                    'score' => 0.85,
                    'numericValue' => 1200,
                    'displayValue' => '1.2 s',
                ],
                'color-contrast' => [
                    'id' => 'color-contrast',
                    'title' => 'Background and foreground colors have a sufficient contrast ratio',
                    'score' => 1,
                ],
            ],
        ];
    }
}
