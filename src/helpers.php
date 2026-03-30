<?php

declare(strict_types=1);

use Myerscode\Beacon\Browser;

if (!function_exists('beacon')) {
    /**
     * Create a configured Browser instance.
     *
     * Usage:
     *   beacon()->visit('https://example.com')->source();
     *   beacon(windowSize: [1920, 1080])->visit('https://example.com')->screenshot('/tmp/shot.png');
     *
     * @param int[]|null $windowSize [width, height] tuple
     * @param string[]   $arguments  Additional Chrome arguments
     */
    function beacon(
        ?array $windowSize = null,
        int $waitTimeout = 10,
        array $arguments = [],
    ): Browser {
        $browser = Browser::create()->waitTimeout($waitTimeout);

        if ($windowSize !== null) {
            $browser->windowSize($windowSize[0], $windowSize[1]);
        }

        foreach ($arguments as $argument) {
            $browser->addArgument($argument);
        }

        return $browser;
    }
}
