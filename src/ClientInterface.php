<?php

declare(strict_types=1);

namespace Myerscode\Beacon;

interface ClientInterface
{
    public function getCrawler(): CrawlerInterface;

    public function getCurrentURL(): string;

    public function getPageSource(): string;

    public function getStatusCode(): int;

    public function getTitle(): string;

    public function quit(): void;

    public function request(string $method, string $uri): void;

    /**
     * Save the page as a PDF file.
     */
    public function savePdf(string $path): void;

    public function takeScreenshot(string $path): void;

    public function waitFor(string $selector, int $timeout = 30): void;

    public function waitForPageReady(int $timeout = 30): void;
}
