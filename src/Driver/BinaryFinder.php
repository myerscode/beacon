<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Driver;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

class BinaryFinder
{
    /**
     * Locate the chromedriver binary on the system.
     *
     * @throws RuntimeException If the binary cannot be found.
     */
    public function find(): string
    {
        $binary = (new ExecutableFinder())->find('chromedriver', null, ['./drivers']);

        if ($binary === null) {
            throw new RuntimeException(
                '"chromedriver" binary not found. Run "composer run driver:install" to install it.',
            );
        }

        return $binary;
    }
}
