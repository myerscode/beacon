<?php

/**
 * Take a screenshot of a page.
 *
 * Usage: php examples/screenshot.php [url] [output-path]
 */

require __DIR__ . '/../vendor/autoload.php';

$url  = $argv[1] ?? 'https://example.com';
$path = $argv[2] ?? '/tmp/beacon-screenshot.png';

echo "Screenshotting: {$url}\n";

beacon(windowSize: [1920, 1080])
    ->visit($url)
    ->screenshot($path);

echo "Saved to: {$path}\n";
