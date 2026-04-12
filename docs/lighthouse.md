# Lighthouse

Beacon can run [Google Lighthouse](https://github.com/GoogleChrome/lighthouse) audits against any page.

## Requirements

- Node.js 16+
- Lighthouse CLI

Install it yourself:

```bash
npm install -g lighthouse
```

Or let Beacon manage it for you:

```bash
composer run lighthouse:install
```

See [Managing Dependencies](advanced-usage.md#managing-dependencies) for the full set of install, update, and remove commands.

## Category Scores

```php
use Myerscode\Beacon\Lighthouse\Category;

$page = beacon()->visit('https://example.com');

// All category scores
$scores = $page->lighthouse();
// ['performance' => 92, 'accessibility' => 100, 'best-practices' => 95, 'seo' => 90]

// Specific categories
$scores = $page->lighthouse(Category::Performance, Category::Seo);
// ['performance' => 92, 'seo' => 90]
```

## Individual Audits

The `audit()` method accepts zero or more arguments. Pass nothing to get all audits, or pass specific ones to filter:

```php
use Myerscode\Beacon\Lighthouse\Audit;

$page = beacon()->visit('https://example.com');

// All audits (~150 results)
$all = $page->audit();

// One specific audit
$fcp = $page->audit(Audit::FirstContentfulPaint);
// ['first-contentful-paint' => ['id' => '...', 'score' => 0.98, 'displayValue' => '0.8 s', ...]]

// Multiple audits
$metrics = $page->audit(
    Audit::FirstContentfulPaint,
    Audit::LargestContentfulPaint,
    Audit::CumulativeLayoutShift,
);

// Mix enums with strings for less common audits
$mixed = $page->audit(Audit::FirstContentfulPaint, 'aria-allowed-attr');
```

The `Audit` enum covers the most commonly used audits with autocomplete support. For the full list of 150+ audit IDs, pass them as strings.

## Configuration

Use `withLighthouseRunner()` for custom Lighthouse settings:

```php
use Myerscode\Beacon\Lighthouse\LighthouseRunner;

$runner = (new LighthouseRunner())
    ->desktop()                                    // Desktop form factor (default: mobile)
    ->chromePath('/usr/bin/google-chrome')          // Custom Chrome binary
    ->lighthouseBinary('/usr/local/bin/lighthouse') // Custom Lighthouse path
    ->timeout(180)                                  // Process timeout in seconds
    ->chromeFlags(['--headless', '--no-sandbox']);   // Custom Chrome flags

$page = beacon()->visit('https://example.com')
    ->withLighthouseRunner($runner);

$scores = $page->lighthouse();
```

## Saving Reports

```php
$page = beacon()->visit('https://example.com');

// Save the full Lighthouse JSON report
$page->lighthouseResult()->saveJson('/tmp/lighthouse-report.json');

// Access the raw data
$raw = $page->lighthouseResult()->raw();
```

Lighthouse results are cached per page — calling `lighthouse()`, `audit()`, and `lighthouseResult()` on the same page only runs Lighthouse once.

## API Reference

### `Page` Lighthouse Methods

| Method | Description |
|---|---|
| `lighthouse(Category ...$categories): array` | Get Lighthouse category scores |
| `audit(Audit\|string ...$audits): array` | Get Lighthouse audit results |
| `lighthouseResult(): LighthouseResult` | Get full Lighthouse result object |
| `withLighthouseRunner(LighthouseRunner $r): Page` | Set custom Lighthouse runner |

### `LighthouseRunner`

| Method | Description |
|---|---|
| `lighthouseBinary(string $path): self` | Custom Lighthouse CLI path |
| `chromePath(string $path): self` | Custom Chrome binary path |
| `desktop(): self` | Use desktop form factor |
| `mobile(): self` | Use mobile form factor (default) |
| `formFactor(string $factor): self` | Set form factor directly |
| `timeout(int $seconds): self` | Process timeout (default: 120) |
| `chromeFlags(array $flags): self` | Set Chrome flags |
| `run(string $url): LighthouseResult` | Run Lighthouse and return result |

### `LighthouseResult`

| Method | Description |
|---|---|
| `scores(Category ...$categories): array` | Category scores (0-100) |
| `audits(Audit\|string ...$audits): array` | Audit results, filtered or all |
| `raw(): array` | Full raw Lighthouse JSON data |
| `saveJson(string $path): self` | Save JSON report to disk |
