<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests;

use Myerscode\Beacon\Browser;
use PHPUnit\Framework\TestCase;

final class BrowserTest extends TestCase
{
    public function testCreateReturnsNewInstance(): void
    {
        $browser = Browser::create();

        $this->assertInstanceOf(Browser::class, $browser);
    }

    public function testWindowSizeReturnsSelf(): void
    {
        $browser = Browser::create();

        $result = $browser->windowSize(1920, 1080);

        $this->assertSame($browser, $result);
    }

    public function testAddArgumentReturnsSelf(): void
    {
        $browser = Browser::create();

        $result = $browser->addArgument('--disable-extensions');

        $this->assertSame($browser, $result);
    }

    public function testWaitTimeoutReturnsSelf(): void
    {
        $browser = Browser::create();

        $result = $browser->waitTimeout(30);

        $this->assertSame($browser, $result);
    }

    public function testChromeBinaryReturnsSelf(): void
    {
        $browser = Browser::create();

        $result = $browser->chromeBinary('/usr/bin/chrome');

        $this->assertSame($browser, $result);
    }

    public function testChromeDriverBinaryReturnsSelf(): void
    {
        $browser = Browser::create();

        $result = $browser->chromeDriverBinary('/usr/bin/chromedriver');

        $this->assertSame($browser, $result);
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

    public function testQuitWithoutClientDoesNotThrow(): void
    {
        $browser = Browser::create();

        $browser->quit();

        $this->assertTrue(true);
    }
}
