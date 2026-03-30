<?php

/**
 * Crawl a site and report all discovered URLs.
 *
 * Usage: php examples/crawl.php [url] [max-depth]
 */

require __DIR__ . '/../vendor/autoload.php';

use Myerscode\Beacon\Crawler\CrawlConfig;

$url      = $argv[1] ?? 'https://example.com';
$maxDepth = (int) ($argv[2] ?? 3);

echo "Crawling: {$url} (max depth: {$maxDepth})\n\n";

$config = (new CrawlConfig())
    ->maxDepth($maxDepth)
    ->maxConcurrent(3)
    ->exclude(['.pdf', '.zip', 'mailto:', '#'])
    ->onCrawled(function (string $url, \Myerscode\Beacon\Crawler\CrawlResult $result): void {
        $type   = $result->internal ? 'internal' : 'external';
        $status = $result->statusCode ?? '---';
        echo "  [{$status}] ({$type}) {$url}\n";
    });

echo "Crawling...\n\n";

$results = beacon()->visit($url)->crawl($config);

echo "Found {$results->count()} URLs\n\n";

$internal = $results->internal();
$external = $results->external();
$broken   = $results->broken();

echo "Internal: " . count($internal) . "\n";
echo "External: " . count($external) . "\n";
echo "Broken:   " . count($broken) . "\n\n";

if ($broken !== []) {
    echo "--- Broken Links ---\n";
    foreach ($broken as $url => $result) {
        echo "  [{$result->statusCode}] {$url}\n";
        foreach ($result->linkedFrom as $source) {
            echo "    <- {$source}\n";
        }
    }
    echo "\n";
}

echo "--- All Internal URLs ---\n";
foreach ($internal as $url => $result) {
    $status = $result->statusCode ?? '???';
    $sources = count($result->linkedFrom);
    echo "  [{$status}] {$url} (depth: {$result->depth}, linked from: {$sources} pages)\n";
}

echo "\n--- External URLs ---\n";
foreach ($external as $url => $result) {
    echo "  {$url}\n";
    foreach ($result->linkedFrom as $source) {
        echo "    <- {$source}\n";
    }
}
