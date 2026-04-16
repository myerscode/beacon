<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests;

use Myerscode\Beacon\Browser;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function optionsProvider(): array
    {
        return [
            'window size'    => [['windowSize' => [1920, 1080]]],
            'arguments'      => [['arguments' => ['--disable-extensions']]],
            'all options'    => [['windowSize' => [1440, 900], 'waitTimeout' => 30, 'arguments' => ['--disable-extensions', '--incognito']]],
        ];
    }
    public function testBeaconFunctionExists(): void
    {
        $this->assertTrue(function_exists('beacon'));
    }

    public function testBeaconReturnsBrowserInstance(): void
    {
        $browser = beacon();

        $this->assertInstanceOf(Browser::class, $browser);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('optionsProvider')]
    public function testBeaconWithOptionsReturnsBrowser(array $options): void
    {
        $browser = beacon(...$options);

        $this->assertInstanceOf(Browser::class, $browser);
    }
}
