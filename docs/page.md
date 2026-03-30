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
```

## Screenshots

```php
$page = beacon()->visit('https://example.com');

// Save a screenshot — returns $page so you can chain
$page->screenshot('/tmp/homepage.png')
     ->screenshot('/tmp/homepage-2.png');
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
| `screenshot(string $path): Page` | Save screenshot |
| `text(string $selector = 'body'): string` | Get element text |
| `attribute(string $selector, string $attr): ?string` | Get element attribute |
| `has(string $selector): bool` | Check element exists |
| `crawler(): CrawlerInterface` | Get underlying Crawler |
| `url(): string` | Original URL visited |
