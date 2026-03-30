<?php

declare(strict_types=1);

namespace Myerscode\Beacon;

interface CrawlerInterface
{
    public function html(): string;

    public function text(?string $default = null): string;

    public function attr(string $attribute): ?string;

    public function filter(string $selector): self;

    public function first(): self;

    public function count(): int;
}
