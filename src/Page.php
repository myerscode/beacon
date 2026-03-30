<?php

declare(strict_types=1);

namespace Myerscode\Beacon;

use Myerscode\Beacon\Crawler\CrawlConfig;
use Myerscode\Beacon\Crawler\CrawlResultCollection;
use Myerscode\Beacon\Crawler\SpiderCrawler;
use Myerscode\Beacon\Lighthouse\Audit;
use Myerscode\Beacon\Lighthouse\Category;
use Myerscode\Beacon\Lighthouse\LighthouseResult;
use Myerscode\Beacon\Lighthouse\LighthouseRunner;

class Page
{
    private ?LighthouseResult $lighthouseResult = null;

    private ?LighthouseRunner $lighthouseRunner = null;

    /**
     * @param Browser|null    $browser Reference to the Browser to prevent GC and share the driver
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly string $url,
        private readonly ?Browser $browser = null,
    ) {
    }

    /**
     * Get the value of an attribute on the first element matching the selector.
     */
    public function attribute(string $selector, string $attribute): ?string
    {
        return $this->client->getCrawler()->filter($selector)->first()->attr($attribute);
    }

    /**
     * Get Lighthouse audit results. No args = all audits.
     *
     * @return array<string, array<string, mixed>>
     */
    public function audit(Audit|string ...$audits): array
    {
        return $this->getLighthouseResult()->audits(...$audits);
    }

    /**
     * Get only the inner HTML of the <body> element.
     */
    public function body(): string
    {
        return $this->client->getCrawler()->filter('body')->html();
    }

    /**
     * Crawl the site starting from this page's URL.
     * Uses the already-loaded DOM to extract seed links, then follows them.
     */
    public function crawl(?CrawlConfig $crawlConfig = null): CrawlResultCollection
    {
        $crawlConfig ??= new CrawlConfig();
        $ownDriver     = null;

        if ($this->browser !== null) {
            $driver = $this->browser->getDriver();
        } else {
            $ownDriver = new ChromeDriverManager();
            $driver    = $ownDriver;
        }

        $spiderCrawler = new SpiderCrawler($crawlConfig, $driver);

        try {
            return $spiderCrawler->crawl($this->url, $this->source());
        } finally {
            $ownDriver?->quit();
        }
    }

    /**
     * Get the underlying Crawler instance for advanced usage.
     */
    public function crawler(): CrawlerInterface
    {
        return $this->client->getCrawler();
    }

    /**
     * Get the current URL (after any redirects).
     */
    public function currentUrl(): string
    {
        return $this->client->getCurrentURL();
    }

    /**
     * Check if an element matching the selector exists on the page.
     */
    public function has(string $selector): bool
    {
        return $this->client->getCrawler()->filter($selector)->count() > 0;
    }

    /**
     * Get all links found on the page.
     *
     * @return string[]
     */
    public function links(): array
    {
        $html = $this->source();

        if (!preg_match_all('/<a\s[^>]*href=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            return [];
        }

        $links = [];

        foreach ($matches[1] as $href) {
            $href = trim($href);

            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                continue;
            }

            $links[] = $href;
        }

        return array_values(array_unique($links));
    }

    /**
     * Get Lighthouse category scores. No args = all categories.
     *
     * @return array<string, int|null>
     */
    public function lighthouse(Category ...$categories): array
    {
        return $this->getLighthouseResult()->scores(...$categories);
    }

    /**
     * Get the full LighthouseResult object for advanced usage.
     */
    public function lighthouseResult(): LighthouseResult
    {
        return $this->getLighthouseResult();
    }

    /**
     * Take a screenshot and save it to the given path.
     */
    public function screenshot(string $path): self
    {
        $this->client->takeScreenshot($path);

        return $this;
    }

    /**
     * Get the fully rendered HTML source of the page.
     */
    public function source(): string
    {
        return $this->client->getPageSource();
    }

    /**
     * Get the text content of an element matching the given CSS selector.
     */
    public function text(string $selector = 'body'): string
    {
        return $this->client->getCrawler()->filter($selector)->first()->text();
    }

    /**
     * Get the page title.
     */
    public function title(): string
    {
        return $this->client->getTitle();
    }

    /**
     * Get the original URL this page was loaded from.
     */
    public function url(): string
    {
        return $this->url;
    }

    /**
     * Set a custom LighthouseRunner for this page.
     */
    public function withLighthouseRunner(LighthouseRunner $lighthouseRunner): self
    {
        $this->lighthouseRunner = $lighthouseRunner;

        return $this;
    }

    private function getLighthouseResult(): LighthouseResult
    {
        if (!$this->lighthouseResult instanceof LighthouseResult) {
            $runner                 = $this->lighthouseRunner ?? new LighthouseRunner();
            $this->lighthouseResult = $runner->run($this->url);
        }

        return $this->lighthouseResult;
    }
}
