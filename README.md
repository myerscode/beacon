[![Latest Stable Version](https://poser.pugx.org/myerscode/beacon/v/stable)](https://packagist.org/packages/myerscode/beacon)
[![Total Downloads](https://poser.pugx.org/myerscode/beacon/downloads)](https://packagist.org/packages/myerscode/beacon)
[![PHP Version Require](http://poser.pugx.org/myerscode/beacon/require/php)](https://packagist.org/packages/myerscode/beacon)
[![License](https://poser.pugx.org/myerscode/beacon/license)](https://github.com/myerscode/beacon/blob/main/LICENSE)
[![Tests](https://github.com/myerscode/beacon/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/myerscode/beacon/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/myerscode/beacon/graph/badge.svg)](https://codecov.io/gh/myerscode/beacon)

# Beacon

A fluent PHP wrapper around [Symfony Panther](https://github.com/symfony/panther) for web page content retrieval and site analysis. Renders JavaScript-heavy pages (SPAs, React, Vue, etc.) and provides an easy API for source code, screenshots, PDFs, meta tags, link extraction, Lighthouse audits, and concurrent site crawling.

## Requirements

- PHP 8.5+
- Chrome/Chromium browser installed
- Node.js 16+ and [Lighthouse CLI](https://www.npmjs.com/package/lighthouse) (only required for Lighthouse features)

## Installation

```bash
composer require myerscode/beacon
```

ChromeDriver is installed automatically via `dbrekelmans/bdi` on `composer install`.

## Quick Start

```php
// Get the fully rendered HTML of any page
$html = beacon()->visit('https://example.com')->source();

// Take a screenshot or save as PDF
beacon()->visit('https://example.com')->screenshot('/tmp/shot.png');
beacon()->visit('https://example.com')->pdf('/tmp/page.pdf');

// Page info
$title  = beacon()->visit('https://example.com')->title();
$status = beacon()->visit('https://example.com')->statusCode();
$links  = beacon()->visit('https://example.com')->links();
$meta   = beacon()->visit('https://example.com')->meta();

// Lighthouse audit
$scores = beacon()->visit('https://example.com')->lighthouse();

// Crawl the site for broken links
$results = beacon()->visit('https://example.com')->crawl();
$broken  = $results->broken();
```

## Documentation

- [Page](docs/page.md) — content, screenshots, PDF, links, meta, status code
- [Lighthouse](docs/lighthouse.md) — category scores, individual audits, configuration, reports
- [Crawler](docs/crawler.md) — concurrent spider crawl, broken link detection, retries, throttling
- [Advanced Usage](docs/advanced-usage.md) — browser configuration, dependency checking

## License

MIT
