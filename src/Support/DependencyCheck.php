<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Support;

class DependencyCheck
{
    public function __construct(
        public readonly string $name,
        public readonly bool $found,
        public readonly ?string $path,
        public readonly ?string $version,
        public readonly string $message,
    ) {
    }

    public function ok(): bool
    {
        return $this->found;
    }
}
