# Page

The `Page` class is what you get back from `beacon()->visit($url)` or `Browser::create()->visit($url)`. Beacon waits for the page to fully load before returning the Page, so all content methods work against the rendered result.

## Getting Page Content

```php
$page = beacon()->visit('https://example.com');

// Full rendered HTML (including <html> wrapper)
$source = $page->source();

// Just the <body> inner HTML
$body = $page->body();

// Page title
$title = $page->title();

// Current URL (after redirects)
$url = $page->currentUrl();

// HTTP status code
$status = $page->statusCode();
```

## Links and Meta

```php
$page = beacon()->visit('https://example.com');

// All unique links found on the page
$links = $page->links();
// ['/about', '/contact', 'https://external.com']

// All meta tags as a keyed array
$meta = $page->meta();
// ['description' => 'A great site', 'og:title' => 'Example', 'og:type' => 'website']
```

## Screenshots and PDF

```php
$page = beacon()->visit('https://example.com');

// Save a screenshot — returns $page so you can chain
$page->screenshot('/tmp/homepage.png')
     ->screenshot('/tmp/homepage-2.png');

// Save as PDF
$page->pdf('/tmp/homepage.pdf');
```

## Querying Elements

```php
$page = beacon()->visit('https://example.com');

// Get text content of an element
$heading = $page->text('h1');

// Get an attribute value
$href = $page->attribute('a.logo', 'href');

// Check if an element exists
if ($page->has('.error-message')) {
    // handle error
}
```

## Crawler Access

For more advanced DOM querying, access the underlying crawler:

```php
$page = beacon()->visit('https://example.com');

$crawler = $page->crawler();
```

## API Reference

| Method | Description |
|---|---|
| `source(): string` | Full rendered HTML |
| `body(): string` | Inner HTML of `<body>` |
| `title(): string` | Page title |
| `currentUrl(): string` | Current URL after redirects |
| `statusCode(): int` | HTTP status code |
| `links(): string[]` | All unique links on the page |
| `meta(): array` | All meta tags as keyed array |
| `screenshot(string $path): Page` | Save screenshot |
| `pdf(string $path): Page` | Save page as PDF |
| `text(string $selector = 'body'): string` | Get element text |
| `attribute(string $selector, string $attr): ?string` | Get element attribute |
| `has(string $selector): bool` | Check element exists |
| `crawl(?CrawlConfig $config): CrawlResultCollection` | Spider crawl the site |
| `lighthouse(Category ...$categories): array` | Lighthouse category scores |
| `audit(Audit\|string ...$audits): array` | Lighthouse audit results |
| `lighthouseResult(): LighthouseResult` | Full Lighthouse result object |
| `withLighthouseRunner(LighthouseRunner $r): Page` | Set custom Lighthouse runner |
| `crawler(): CrawlerInterface` | Get underlying Crawler |
| `url(): string` | Original URL visited |
