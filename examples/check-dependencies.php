<?php

/**
 * Check that all required dependencies are installed.
 *
 * Usage: php examples/check-dependencies.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Myerscode\Beacon\Support\DependencyChecker;

echo "Beacon Dependency Check\n";
echo "=======================\n\n";

$results  = (new DependencyChecker())->check();
$allFound = true;

foreach ($results as $check) {
    $icon = $check->ok() ? '✓' : '✗';
    echo "  {$icon} {$check->name}: {$check->message}\n";

    if (!$check->ok()) {
        $allFound = false;
    }
}

echo "\n";

if ($allFound) {
    echo "All dependencies found.\n";
    exit(0);
}

echo "Some dependencies are missing. Core features require Chrome and ChromeDriver.\n";
echo "Lighthouse features additionally require Node.js and the Lighthouse CLI.\n";
exit(1);
