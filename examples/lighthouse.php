<?php

/**
 * Run a Lighthouse audit on a page.
 * Requires: npm install -g lighthouse
 *
 * Usage: php examples/lighthouse.php [url]
 */

require __DIR__ . '/../vendor/autoload.php';

use Myerscode\Beacon\Lighthouse\Audit;
use Myerscode\Beacon\Lighthouse\Category;
use Myerscode\Beacon\Lighthouse\LighthouseRunner;

$url = $argv[1] ?? 'https://example.com';

echo "Running Lighthouse audit: {$url}\n\n";

$runner = (new LighthouseRunner())->desktop();

$page = beacon()->visit($url)->withLighthouseRunner($runner);

// Category scores
$scores = $page->lighthouse();

echo "--- Category Scores ---\n";
foreach ($scores as $category => $score) {
    $bar = str_repeat('█', (int) ($score / 5)) . str_repeat('░', 20 - (int) ($score / 5));
    echo "  {$category}: {$bar} {$score}/100\n";
}

echo "\n--- Core Web Vitals ---\n";

$vitals = $page->audit(
    Audit::FirstContentfulPaint,
    Audit::LargestContentfulPaint,
    Audit::TotalBlockingTime,
    Audit::CumulativeLayoutShift,
    Audit::SpeedIndex,
);

foreach ($vitals as $id => $audit) {
    $display = $audit['displayValue'] ?? 'N/A';
    $score   = isset($audit['score']) ? round($audit['score'] * 100) : 'N/A';
    echo "  {$audit['title']}: {$display} (score: {$score})\n";
}
