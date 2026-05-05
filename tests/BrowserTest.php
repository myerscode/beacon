<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests;

use Myerscode\Beacon\Browser;
use Myerscode\Beacon\ClientFactory;
use Myerscode\Beacon\ClientInterface;
use Myerscode\Beacon\Page;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

final class BrowserTest extends TestCase
{
    public function testAddArgumentReturnsSelf(): void
    {
        $browser = Browser::create();

        $this->assertSame($browser, $browser->addArgument('--disable-extensions'));
    }

    public function testChromeBinaryReturnsSelf(): void
    {
        $browser = Browser::create();

        $this->assertSame($browser, $browser->chromeBinary('/usr/bin/chrome'));
    }

    public function testChromeDriverBinaryReturnsSelf(): void
    {
        $browser = Browser::create();

        $this->assertSame($browser, $browser->chromeDriverBinary('/usr/bin/chromedriver'));
    }

    public function testClientFactoryReturnsSelf(): void
    {
        $browser = Browser::create();
        $factory = $this->createStub(ClientFactory::class);

        $this->assertSame($browser, $browser->clientFactory($factory));
    }
    // =========================================================================
    // Factory and fluent API
    // =========================================================================

    public function testCreateReturnsNewInstance(): void
    {
        $this->assertInstanceOf(Browser::class, Browser::create());
    }

    public function testFluentChaining(): void
    {
        $browser = Browser::create()
            ->windowSize(1920, 1080)
            ->addArgument('--disable-extensions')
            ->waitTimeout(20)
            ->chromeBinary('/usr/bin/chrome')
            ->chromeDriverBinary('/usr/bin/chromedriver');

        $this->assertInstanceOf(Browser::class, $browser);
    }

    public function testQuitCanBeCalledMultipleTimes(): void
    {
        $browser = Browser::create();
        $browser->quit();
        $browser->quit();

        $ref = new ReflectionProperty(Browser::class, 'chromeDriverManager');
        $this->assertNull($ref->getValue($browser));
    }

    public function testQuitResetsClient(): void
    {
        $browser = Browser::create();
        $browser->quit();

        $ref = new ReflectionProperty(Browser::class, 'client');
        $this->assertNull($ref->getValue($browser));
    }

    // =========================================================================
    // quit()
    // =========================================================================

    public function testQuitResetsDriverManager(): void
    {
        $browser = Browser::create();
        $browser->quit();

        $ref = new ReflectionProperty(Browser::class, 'chromeDriverManager');
        $this->assertNull($ref->getValue($browser));
    }

    public function testVisitAllCallsWaitForPageReadyWithDefaultTimeout(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('request');
        $client->expects($this->once())->method('waitForPageReady')->with(10);

        $browser = Browser::create()->clientFactory($this->createFactoryReturning($client));

        $browser->visitAll(['https://example.com']);
    }

    public function testVisitAllCreatesOneClientPerUrl(): void
    {
        $factory = $this->createMock(ClientFactory::class);
        $factory->expects($this->exactly(3))
            ->method('create')
            ->willReturn($this->createStub(ClientInterface::class));

        $browser = Browser::create()->clientFactory($factory);

        $browser->visitAll(['https://a.com', 'https://b.com', 'https://c.com']);
    }

    public function testVisitAllEachClientReceivesCorrectUrl(): void
    {
        $clientA = $this->createMock(ClientInterface::class);
        $clientA->expects($this->once())->method('request')->with('GET', 'https://a.com');
        $clientA->expects($this->once())->method('waitForPageReady');

        $clientB = $this->createMock(ClientInterface::class);
        $clientB->expects($this->once())->method('request')->with('GET', 'https://b.com');
        $clientB->expects($this->once())->method('waitForPageReady');

        $factory = $this->createFactoryReturningSequence([$clientA, $clientB]);
        $browser = Browser::create()->clientFactory($factory);

        $pages = $browser->visitAll(['https://a.com', 'https://b.com']);

        $this->assertCount(2, $pages);
    }

    public function testVisitAllHandlesClientExceptionGracefully(): void
    {
        $badClient = $this->createStub(ClientInterface::class);
        $badClient->method('request')
            ->willThrowException(new RuntimeException('Connection refused'));

        $goodClient = $this->createStub(ClientInterface::class);

        $factory = $this->createFactoryReturningSequence([$badClient, $goodClient]);
        $browser = Browser::create()->clientFactory($factory);

        $pages = $browser->visitAll(['https://bad.com', 'https://good.com']);

        $this->assertCount(1, $pages);
        $this->assertSame('https://good.com', $pages[0]->url());
    }

    public function testVisitAllPreservesUrlOrder(): void
    {
        $browser = Browser::create()->clientFactory($this->createStubFactory(3));

        $pages = $browser->visitAll(['https://first.com', 'https://second.com', 'https://third.com']);

        $this->assertSame('https://first.com', $pages[0]->url());
        $this->assertSame('https://second.com', $pages[1]->url());
        $this->assertSame('https://third.com', $pages[2]->url());
    }

    // =========================================================================
    // visitAll() — empty input
    // =========================================================================

    public function testVisitAllReturnsEmptyArrayForEmptyInput(): void
    {
        $this->assertSame([], Browser::create()->visitAll([]));
    }

    public function testVisitAllReturnsEmptyWhenAllClientsFail(): void
    {
        $bad1 = $this->createStub(ClientInterface::class);
        $bad1->method('request')->willThrowException(new RuntimeException('fail'));

        $bad2 = $this->createStub(ClientInterface::class);
        $bad2->method('request')->willThrowException(new RuntimeException('fail'));

        $factory = $this->createFactoryReturningSequence([$bad1, $bad2]);
        $browser = Browser::create()->clientFactory($factory);

        $this->assertSame([], $browser->visitAll(['https://a.com', 'https://b.com']));
    }

    // =========================================================================
    // visitAll() — with injected factory
    // =========================================================================

    public function testVisitAllReturnsPageForEachUrl(): void
    {
        $browser = Browser::create()->clientFactory($this->createStubFactory(3));

        $pages = $browser->visitAll(['https://a.com', 'https://b.com', 'https://c.com']);

        $this->assertCount(3, $pages);

        foreach ($pages as $page) {
            $this->assertInstanceOf(Page::class, $page);
        }
    }

    public function testVisitAllUsesConfiguredWaitTimeout(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('request');
        $client->expects($this->once())->method('waitForPageReady')->with(25);

        $browser = Browser::create()
            ->waitTimeout(25)
            ->clientFactory($this->createFactoryReturning($client));

        $browser->visitAll(['https://example.com']);
    }

    public function testVisitAllWithConcurrencyHigherThanUrlCount(): void
    {
        $browser = Browser::create()->clientFactory($this->createStubFactory(2));

        $pages = $browser->visitAll(['https://a.com', 'https://b.com'], concurrency: 10);

        $this->assertCount(2, $pages);
    }

    public function testVisitAllWithConcurrencyOfOne(): void
    {
        $browser = Browser::create()->clientFactory($this->createStubFactory(3));

        $pages = $browser->visitAll(
            ['https://a.com', 'https://b.com', 'https://c.com'],
            concurrency: 1,
        );

        $this->assertCount(3, $pages);
        $this->assertSame('https://a.com', $pages[0]->url());
        $this->assertSame('https://b.com', $pages[1]->url());
        $this->assertSame('https://c.com', $pages[2]->url());
    }

    public function testVisitAllWithManyUrlsAndLowConcurrency(): void
    {
        $count = 10;
        $urls  = [];

        for ($i = 0; $i < $count; $i++) {
            $urls[] = "https://site-{$i}.com";
        }

        $browser = Browser::create()->clientFactory($this->createStubFactory($count));

        $pages = $browser->visitAll($urls, concurrency: 2);

        $this->assertCount($count, $pages);

        for ($i = 0; $i < $count; $i++) {
            $this->assertSame("https://site-{$i}.com", $pages[$i]->url());
        }
    }

    public function testVisitAllWithSingleUrl(): void
    {
        $browser = Browser::create()->clientFactory($this->createStubFactory(1));

        $pages = $browser->visitAll(['https://only.com']);

        $this->assertCount(1, $pages);
        $this->assertSame('https://only.com', $pages[0]->url());
    }

    // =========================================================================
    // visit()
    // =========================================================================

    public function testVisitReturnsPage(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('request')->with('GET', 'https://example.com');
        $client->expects($this->once())->method('waitForPageReady')->with(10);

        $browser = Browser::create()->clientFactory($this->createFactoryReturning($client));

        $page = $browser->visit('https://example.com');

        $this->assertInstanceOf(Page::class, $page);
        $this->assertSame('https://example.com', $page->url());
    }

    public function testVisitReusesSameClient(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(2))->method('request');
        $client->expects($this->exactly(2))->method('waitForPageReady');

        $factory = $this->createMock(ClientFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($client);

        $browser = Browser::create()->clientFactory($factory);

        $browser->visit('https://a.com');
        $browser->visit('https://b.com');
    }

    public function testVisitUsesConfiguredTimeout(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('request');
        $client->expects($this->once())->method('waitForPageReady')->with(30);

        $browser = Browser::create()
            ->waitTimeout(30)
            ->clientFactory($this->createFactoryReturning($client));

        $browser->visit('https://example.com');
    }

    public function testWaitTimeoutReturnsSelf(): void
    {
        $browser = Browser::create();

        $this->assertSame($browser, $browser->waitTimeout(30));
    }

    public function testWindowSizeReturnsSelf(): void
    {
        $browser = Browser::create();

        $this->assertSame($browser, $browser->windowSize(1920, 1080));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Create a factory that always returns the same client.
     */
    private function createFactoryReturning(ClientInterface $client): ClientFactory
    {
        $factory = $this->createStub(ClientFactory::class);
        $factory->method('create')->willReturn($client);

        return $factory;
    }

    /**
     * Create a factory that returns clients from a list in order.
     *
     * @param ClientInterface[] $clients
     */
    private function createFactoryReturningSequence(array $clients): ClientFactory
    {
        $factory = $this->createStub(ClientFactory::class);
        $factory->method('create')->willReturn(...$clients);

        return $factory;
    }

    /**
     * Create a factory that returns stub clients.
     */
    private function createStubFactory(int $count): ClientFactory
    {
        $clients = [];

        for ($i = 0; $i < $count; $i++) {
            $clients[] = $this->createStub(ClientInterface::class);
        }

        return $this->createFactoryReturningSequence($clients);
    }
}
