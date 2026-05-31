# Advanced Usage

## Browser Configuration

For more control over the Chrome instance, use the `Browser` class directly instead of the `beacon()` helper:

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

### Browser API

| Method | Description |
|---|---|
| `create(): Browser` | Static factory (spawns its own ChromeDriver) |
| `connectTo(string $url): Browser` | Connect to an external ChromeDriver |
| `windowSize(int $w, int $h): Browser` | Set viewport size |
| `waitTimeout(int $seconds): Browser` | Set page load wait timeout |
| `addArgument(string $arg): Browser` | Add a Chrome CLI argument |
| `chromeBinary(string $path): Browser` | Custom Chrome binary path |
| `chromeDriverBinary(string $path): Browser` | Custom ChromeDriver path |
| `visit(string $url): Page` | Navigate to URL, returns Page |
| `visitAll(array $urls): Page[]` | Visit multiple URLs concurrently |
| `quit(): void` | Close browser and clean up |

> **Remote ChromeDriver:** For long-running processes (queue workers, daemons), use `Browser::connectTo()` to connect to an externally managed ChromeDriver. See [Remote Driver](remote-driver.md) for details.

## Managing Dependencies

Beacon includes built-in commands for managing ChromeDriver and the Lighthouse CLI so you don't need any external tools.

### ChromeDriver

ChromeDriver is installed automatically on `composer install` and updated on `composer update`. It detects your installed Chrome version and downloads the matching driver for your platform.

You can also run the commands manually:

```bash
# Install ChromeDriver matching your Chrome version (skips if already up to date)
composer run driver:install

# Force re-download (useful after a Chrome update)
composer run driver:update

# Remove the ChromeDriver binary from ./drivers
composer run driver:clean
```

### Lighthouse CLI

Lighthouse is optional — only needed if you use `lighthouse()` or `audit()`. You can install it yourself with npm, or use the Beacon commands:

```bash
# Install Lighthouse CLI globally via npm
composer run lighthouse:install

# Update to the latest version
composer run lighthouse:update

# Remove the global installation
composer run lighthouse:remove
```

### Running in a Consuming Project

When Beacon is installed as a dependency in another project, the `composer run` scripts above are not available because Composer only runs scripts defined in the root `composer.json`. Instead, use the binary commands directly:

```bash
# ChromeDriver
vendor/bin/beacon-driver install
vendor/bin/beacon-driver update
vendor/bin/beacon-driver clean

# Lighthouse
vendor/bin/beacon-lighthouse install
vendor/bin/beacon-lighthouse update
vendor/bin/beacon-lighthouse remove
```

To automate ChromeDriver installation in your project, add the following to your `composer.json`:

```json
{
    "scripts": {
        "post-install-cmd": ["@php vendor/bin/beacon-driver install"],
        "post-update-cmd": ["@php vendor/bin/beacon-driver update"]
    }
}
```

### Dependency Check

Verify all required dependencies are present on the system:

```php
use Myerscode\Beacon\Support\DependencyChecker;

foreach ((new DependencyChecker())->check() as $check) {
    $icon = $check->ok() ? '✓' : '✗';
    echo "{$icon} {$check->name}: {$check->message}\n";
}
```

The checker validates:
- Chrome/Chromium — browser binary
- ChromeDriver — WebDriver bridge (version must match Chrome)
- Node.js — required for Lighthouse features
- Lighthouse CLI — required for Lighthouse features

You can also check individual dependencies:

```php
$checker = new DependencyChecker();

$chrome = $checker->chrome();
$driver = $checker->chromeDriver();
$node   = $checker->node();
$lh     = $checker->lighthouse();

if (!$chrome->ok()) {
    echo $chrome->message; // Includes install instructions
}
```

There's also an example script you can run from the command line:

```bash
php examples/check-dependencies.php
```
