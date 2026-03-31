<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Crawler;

use Myerscode\Beacon\ChromeDriverManager;
use Myerscode\Beacon\Crawler\Spider;
use Myerscode\Beacon\Crawler\CrawlConfig;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class CrawlerUrlTest extends TestCase
{
    private Spider $crawler;

    protected function setUp(): void
    {
        $this->crawler = new Spider(new CrawlConfig(), $this->createStub(ChromeDriverManager::class));

        $reflectionClass = new ReflectionClass($this->crawler);

        $reflectionClass->getProperty('baseScheme')->setValue($this->crawler, 'https');
        $reflectionClass->getProperty('baseHost')->setValue($this->crawler, 'example.com');
        $reflectionClass->getProperty('baseUrl')->setValue($this->crawler, 'https://example.com');
    }

    public function testIsInternalDifferentDomain(): void
    {
        $this->assertFalse($this->isInternal('https://other.com/page'));
    }

    public function testIsInternalSameDomain(): void
    {
        $this->assertTrue($this->isInternal('https://example.com/page'));
    }

    public function testNormalizeUrlStripsFragment(): void
    {
        $this->assertSame(
            'https://example.com/page',
            $this->normalizeUrl('https://example.com/page#section'),
        );
    }

    public function testNormalizeUrlStripsTrailingSlash(): void
    {
        $this->assertSame(
            'https://example.com/page',
            $this->normalizeUrl('https://example.com/page/'),
        );
    }

    public function testResolveAbsoluteUrl(): void
    {
        $this->assertSame(
            'https://example.com/about',
            $this->resolveUrl('https://example.com/about', 'https://example.com/'),
        );
    }

    public function testResolveIgnoresEmpty(): void
    {
        $this->assertSame('', $this->resolveUrl('', 'https://example.com/'));
    }

    public function testResolveIgnoresHashOnly(): void
    {
        $this->assertSame('', $this->resolveUrl('#section', 'https://example.com/'));
    }

    public function testResolveIgnoresJavascript(): void
    {
        $this->assertSame('', $this->resolveUrl('javascript:void(0)', 'https://example.com/'));
    }

    public function testResolveIgnoresMailto(): void
    {
        $this->assertSame('', $this->resolveUrl('mailto:test@example.com', 'https://example.com/'));
    }

    public function testResolveIgnoresTel(): void
    {
        $this->assertSame('', $this->resolveUrl('tel:+1234567890', 'https://example.com/'));
    }

    public function testResolveProtocolRelativeUrl(): void
    {
        $this->assertSame(
            'https://cdn.example.com/file.js',
            $this->resolveUrl('//cdn.example.com/file.js', 'https://example.com/'),
        );
    }

    public function testResolveRelativeUrl(): void
    {
        $this->assertSame(
            'https://example.com/blog/post',
            $this->resolveUrl('post', 'https://example.com/blog/index'),
        );
    }

    public function testResolveRootRelativeUrl(): void
    {
        $this->assertSame(
            'https://example.com/about',
            $this->resolveUrl('/about', 'https://example.com/page'),
        );
    }

    public function testResolveStripsFragment(): void
    {
        $this->assertSame(
            'https://example.com/page',
            $this->resolveUrl('https://example.com/page#section', 'https://example.com/'),
        );
    }

    private function isInternal(string $url): bool
    {
        $reflectionMethod = new ReflectionMethod($this->crawler, 'isInternal');

        return $reflectionMethod->invoke($this->crawler, $url);
    }

    private function normalizeUrl(string $url): string
    {
        $reflectionMethod = new ReflectionMethod($this->crawler, 'normalizeUrl');

        return $reflectionMethod->invoke($this->crawler, $url);
    }

    private function resolveUrl(string $href, string $currentPage): string
    {
        $reflectionMethod = new ReflectionMethod($this->crawler, 'resolveUrl');

        return $reflectionMethod->invoke($this->crawler, $href, $currentPage);
    }
}
