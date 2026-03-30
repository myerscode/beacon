<?php

/**
 * Get the fully rendered HTML source of a page.
 *
 * Usage: php examples/source.php [url]
 */

require __DIR__ . '/../vendor/autoload.php';

$url = $argv[1] ?? 'https://example.com';

echo "Fetching source for: {$url}\n\n";

$page = beacon()->visit($url);

echo "Title: {$page->title()}\n";
echo "URL:   {$page->currentUrl()}\n";
echo "---\n";
echo substr($page->source(), 0, 2000) . "\n...\n";
