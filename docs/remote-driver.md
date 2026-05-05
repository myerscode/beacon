# Remote ChromeDriver

By default, Beacon spawns and manages its own ChromeDriver process. For long-running applications (queue workers, daemons, test suites), you can connect to an externally managed ChromeDriver instead.

## Why Use a Remote Driver?

- **Queue workers** — avoid spawning a new ChromeDriver per job
- **Daemons** — keep a single ChromeDriver alive across many operations
- **CI/CD** — connect to a ChromeDriver service container
- **Shared infrastructure** — multiple processes share one driver

## Connecting to a Remote Driver

Use `Browser::connectTo()` to create a Browser that connects to an already-running ChromeDriver:

```php
use Myerscode\Beacon\Browser;

// Connect to ChromeDriver running on port 9515
$browser = Browser::connectTo('http://127.0.0.1:9515');

$page = $browser->visit('https://example.com');
echo $page->title();

// quit() only closes the browser session — it does NOT stop ChromeDriver
$browser->quit();
```

### With Custom Chrome Arguments

```php
$browser = Browser::connectTo('http://127.0.0.1:9515', [
    '--headless=new',
    '--disable-gpu',
    '--window-size=1920,1080',
]);
```

## Starting ChromeDriver Externally

Start ChromeDriver on a fixed port before your application:

```bash
chromedriver --port=9515
```

Or use a process manager (systemd, supervisord, Docker) to keep it alive:

```ini
# /etc/supervisor/conf.d/chromedriver.conf
[program:chromedriver]
command=/usr/local/bin/chromedriver --port=9515
autostart=true
autorestart=true
```

## How It Works

`Browser::connectTo()` uses a `RemoteClientFactory` internally. Each call to `visit()` creates a new Chrome session on the remote driver — sessions are independent and isolated.

The Browser does **not** manage the ChromeDriver lifecycle when using `connectTo()`:
- `quit()` closes the browser session only
- ChromeDriver continues running for other consumers
- If ChromeDriver dies, the next `visit()` will throw — your application should handle reconnection

## Using the ClientFactory Directly

For advanced use cases, you can use `RemoteClientFactory` directly:

```php
use Myerscode\Beacon\Client\RemoteClientFactory;

$factory = new RemoteClientFactory('http://127.0.0.1:9515');

// Each create() call returns a fresh, independent session
$client = $factory->create();
$client->request('GET', 'https://example.com');
$client->waitForPageReady(10);

echo $client->getPageSource();
$client->quit();
```

## Fallback Pattern

A common pattern is to try the remote driver first, falling back to a local one:

```php
use Myerscode\Beacon\Browser;

function createBrowser(): Browser
{
    $port = 9515;
    $socket = @stream_socket_client("tcp://127.0.0.1:{$port}", timeout: 1);

    if ($socket !== false) {
        fclose($socket);
        return Browser::connectTo("http://127.0.0.1:{$port}");
    }

    return Browser::create();
}
```
