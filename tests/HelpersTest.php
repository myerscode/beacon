<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests;

use Myerscode\Beacon\Browser;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testBeaconFunctionExists(): void
    {
        $this->assertTrue(function_exists('beacon'));
    }

    public function testBeaconReturnsBrowserInstance(): void
    {
        $browser = beacon();

        $this->assertInstanceOf(Browser::class, $browser);
    }

    public function testBeaconWithAllOptionsReturnsBrowser(): void
    {
        $browser = beacon(
            windowSize: [1440, 900],
            waitTimeout: 30,
            arguments: ['--disable-extensions', '--incognito'],
        );

        $this->assertInstanceOf(Browser::class, $browser);
    }

    public function testBeaconWithArgumentsReturnsBrowser(): void
    {
        $browser = beacon(arguments: ['--disable-extensions']);

        $this->assertInstanceOf(Browser::class, $browser);
    }

    public function testBeaconWithWindowSizeReturnsBrowser(): void
    {
        $browser = beacon(windowSize: [1920, 1080]);

        $this->assertInstanceOf(Browser::class, $browser);
    }
}
