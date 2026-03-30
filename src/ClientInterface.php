<?php

declare(strict_types=1);

namespace Myerscode\Beacon;

interface ClientInterface
{
    public function getCrawler(): CrawlerInterface;

    public function getTitle(): string;

    public function getCurrentURL(): string;

    public function takeScreenshot(string $path): void;

    public function waitFor(string $selector, int $timeout = 30): void;

    public function getPageSource(): string;

    public function waitForPageReady(int $timeout = 30): void;

    public function request(string $method, string $uri): void;

    public function quit(): void;
}
