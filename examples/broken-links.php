<?php

/**
 * Find broken links on a site.
 *
 * Usage: php examples/broken-links.php [url]
 */

require __DIR__ . '/../vendor/autoload.php';

use Myerscode\Beacon\Crawler\CrawlConfig;

$url = $argv[1] ?? 'https://example.com';

echo "Checking for broken links: {$url}\n\n";

$config = (new CrawlConfig())
    ->maxDepth(2)
    ->maxConcurrent(3);

$results = beacon()->visit($url)->crawl($config);
$broken  = $results->broken();

if ($broken === []) {
    echo "No broken links found across {$results->count()} URLs.\n";
    exit(0);
}

echo "Found " . count($broken) . " broken links:\n\n";

foreach ($broken as $url => $result) {
    echo "  [{$result->statusCode}] {$url}\n";
    foreach ($result->linkedFrom as $source) {
        echo "         linked from: {$source}\n";
    }
    echo "\n";
}

exit(1);
