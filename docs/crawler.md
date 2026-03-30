# Crawler

Beacon includes a spider crawler that follows links across your site using Chrome to render each page (so JS-rendered links are captured). It tracks every URL it finds, whether internal or external, where each was linked from, and the HTTP status code.

## Basic Usage

```php
$results = beacon()->visit('https://example.com')->crawl();

// How many URLs found
echo $results->count();

// Iterate over results
foreach ($results as $url => $result) {
    echo "{$url} — {$result->statusCode}\n";
}
```

## Configuration

Pass a `CrawlConfig` to control the crawl behaviour:

```php
use Myerscode\Beacon\Crawler\CrawlConfig;

$config = (new CrawlConfig())
    ->maxDepth(4)           // Only go 4 levels deep (default: 5)
    ->maxConcurrent(3)      // 3 Chrome instances in parallel (default: 5)
    ->timeout(15)           // Page load timeout in seconds (default: 30)
    ->exclude(['/admin', '/logout', '.pdf'])  // Skip URLs containing these strings
    ->shouldCrawl(fn (string $url) => !str_contains($url, 'private'));

$results = beacon()->visit('https://example.com')->crawl($config);
```

### Exclude Patterns

Patterns are matched with `str_contains` against the full URL:

```php
$config = (new CrawlConfig())->exclude([
    '/admin',       // Skip admin pages
    '/api/',        // Skip API endpoints
    '.pdf',         // Skip PDF files
    'logout',       // Skip logout URLs
]);
```

### Custom Crawl Filter

The `shouldCrawl` closure receives the URL and returns a bool. It runs after exclude patterns, so excludes take precedence:

```php
$config = (new CrawlConfig())->shouldCrawl(function (string $url): bool {
    // Only crawl blog pages
    return str_contains($url, '/blog');
});
```

## Working with Results

The `CrawlResultCollection` provides several ways to filter and query results:

```php
$results = beacon()->visit('https://example.com')->crawl();

// All results
$results->all();

// Only internal URLs
$results->internal();

// Only external URLs
$results->external();

// URLs with a specific status code
$results->withStatus(404);

// All broken links (4xx and 5xx)
$results->broken();

// Check if a specific URL was found
$results->has('https://example.com/about');

// Get a specific result
$result = $results->get('https://example.com/about');
```

### CrawlResult Properties

Each `CrawlResult` contains:

```php
$result = $results->get('https://example.com/about');

$result->url;        // 'https://example.com/about'
$result->internal;   // true (same domain as start URL)
$result->statusCode; // 200 (null for external URLs that weren't visited)
$result->linkedFrom; // ['https://example.com', 'https://example.com/contact']
$result->depth;      // 1 (how many clicks from the start URL)
```

### Finding Broken Links

```php
$results = beacon()->visit('https://example.com')->crawl();

foreach ($results->broken() as $url => $result) {
    echo "Broken: {$url} (HTTP {$result->statusCode})\n";
    echo "  Linked from:\n";
    foreach ($result->linkedFrom as $source) {
        echo "    - {$source}\n";
    }
}
```

## How It Works

- Uses Panther (Chrome) to render each page, so JS-generated links are captured
- Resolves relative URLs, protocol-relative URLs, and root-relative URLs
- Strips fragments (#) and normalises trailing slashes to avoid duplicates
- Skips `mailto:`, `tel:`, `javascript:`, and empty hrefs
- Records external links but doesn't follow them
- Spins up multiple Chrome instances for concurrent crawling

## API Reference

### `CrawlConfig`

| Method | Description |
|---|---|
| `maxDepth(int $depth): self` | Max link depth to follow (default: 5) |
| `maxConcurrent(int $n): self` | Chrome instances in pool (default: 5) |
| `timeout(int $seconds): self` | Page load timeout (default: 30) |
| `exclude(array $patterns): self` | URL patterns to skip |
| `shouldCrawl(Closure $fn): self` | Custom filter closure |

### `CrawlResultCollection`

| Method | Description |
|---|---|
| `all(): array` | All results |
| `internal(): array` | Internal URLs only |
| `external(): array` | External URLs only |
| `withStatus(int $code): array` | Filter by status code |
| `broken(): array` | 4xx and 5xx results |
| `has(string $url): bool` | Check if URL exists |
| `get(string $url): ?CrawlResult` | Get specific result |
| `count(): int` | Total URL count |

### `CrawlResult`

| Property | Type | Description |
|---|---|---|
| `url` | `string` | The URL |
| `internal` | `bool` | Same domain as start URL |
| `statusCode` | `?int` | HTTP status (null if not fetched) |
| `linkedFrom` | `string[]` | Pages that link to this URL |
| `depth` | `int` | Depth from start URL |
