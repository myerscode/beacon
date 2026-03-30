# Beacon

A fluent PHP wrapper around [Symfony Panther](https://github.com/symfony/panther) that makes interacting with web pages simple. Renders JavaScript-heavy pages (SPAs, React, Vue, etc.) and provides an easy API for grabbing source code, taking screenshots, interacting with page elements, and running Lighthouse audits.

## Requirements

- PHP 8.5+
- Chrome/Chromium browser installed
- Node.js 16+ and [Lighthouse CLI](https://www.npmjs.com/package/lighthouse) (only required for Lighthouse features)

## Installation

```bash
composer require myerscode/beacon
```

After installing, run the driver installer to download a matching ChromeDriver:

```bash
vendor/bin/bdi detect drivers
```

## Quick Start

The fastest way to get going is with the `beacon()` helper:

```php
// Get the fully rendered HTML of any page
$html = beacon()->visit('https://example.com')->source();

// Take a screenshot
beacon()->visit('https://example.com')->screenshot('/tmp/example.png');

// Get just the page title
$title = beacon()->visit('https://example.com')->title();

// Configure the browser inline
$html = beacon(windowSize: [1920, 1080], waitTimeout: 15)
    ->visit('https://example.com')
    ->source();

// Run a Lighthouse audit
$scores = beacon()->visit('https://example.com')->lighthouse();

// Get specific audit details
$fcp = beacon()->visit('https://example.com')->audit(Audit::FirstContentfulPaint);

// Crawl the site for broken links
$results = beacon()->visit('https://example.com')->crawl();
$broken  = $results->broken();
```

## Browser Configuration

For more control, use the `Browser` class directly:

```php
use Myerscode\Beacon\Browser;

$browser = Browser::create()
    ->windowSize(1920, 1080)
    ->waitTimeout(15)
    ->chromeBinary('/usr/bin/google-chrome')
    ->chromeDriverBinary('/usr/local/bin/chromedriver')
    ->addArgument('--disable-extensions');

$page = $browser->visit('https://example.com');

// Done? Clean up.
$browser->quit();
```

## Documentation

- [Page](docs/page.md) — content retrieval, screenshots, element querying
- [Lighthouse](docs/lighthouse.md) — category scores, individual audits, configuration, saving reports
- [Crawler](docs/crawler.md) — spider crawl, broken link detection, site mapping

## API Reference

### `beacon(?array $windowSize, int $waitTimeout, array $arguments): Browser`

Helper function. Creates and configures a Browser instance. Chain `->visit($url)` to get a Page.

### `Browser`

| Method | Description |
|---|---|
| `create(): Browser` | Static factory |
| `windowSize(int $w, int $h): Browser` | Set viewport size |
| `waitTimeout(int $seconds): Browser` | Set page load wait timeout |
| `addArgument(string $arg): Browser` | Add a Chrome CLI argument |
| `chromeBinary(string $path): Browser` | Custom Chrome binary path |
| `chromeDriverBinary(string $path): Browser` | Custom ChromeDriver path |
| `visit(string $url): Page` | Navigate to URL, returns Page |
| `quit(): void` | Close browser and clean up |

See [Page API](docs/page.md#api-reference) and [Lighthouse API](docs/lighthouse.md#api-reference) for the full method reference.

## License

MIT
