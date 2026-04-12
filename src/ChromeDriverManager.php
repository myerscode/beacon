<?php

declare(strict_types=1);

namespace Myerscode\Beacon;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use RuntimeException;
use Symfony\Component\Panther\Client;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class ChromeDriverManager
{
    /**
     * @var self[]
     */
    private static array $instances = [];

    private static bool $shutdownRegistered = false;

    /**
     * @var Client[]
     */
    private array $clients = [];

    private string $host = '127.0.0.1';

    private ?int $pid = null;

    private readonly int $port;

    private ?Process $process = null;

    /**
     * @param string[] $chromeArguments
     */
    public function __construct(
        private readonly array $chromeArguments = ['--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage'],
        ?string $chromeDriverBinary = null,
    ) {
        $this->port = random_int(10000, 60000);

        $binary = $chromeDriverBinary ?? $this->findBinary();

        $this->process = new Process([$binary, '--port=' . $this->port], null, null, null, null);
        $this->process->start();
        $this->pid = $this->process->getPid();

        $this->registerForCleanup();
        $this->waitUntilReady();
    }

    public function __destruct()
    {
        $this->quit();
    }

    /**
     * Force cleanup of all active ChromeDriver instances.
     * Call this in your own shutdown handlers if needed.
     */
    public static function cleanup(): void
    {
        foreach (self::$instances as $instance) {
            try {
                $instance->quit();
            } catch (Throwable) {
                // Best effort
            }
        }

        self::$instances = [];
    }

    /**
     * Create a new Panther Client session on this driver.
     */
    public function createClient(): Client
    {
        $url                 = sprintf('http://%s:%d', $this->host, $this->port);
        $desiredCapabilities = DesiredCapabilities::chrome();

        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments($this->chromeArguments);

        $desiredCapabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);

        $client = Client::createSeleniumClient($url, $desiredCapabilities);

        $this->clients[] = $client;

        return $client;
    }

    /**
     * Get the port this driver is running on.
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Get the base URL for connecting to this driver.
     */
    public function getUrl(): string
    {
        return sprintf('http://%s:%d', $this->host, $this->port);
    }

    /**
     * Shut down all clients and the driver process.
     */
    public function quit(): void
    {
        foreach ($this->clients as $client) {
            try {
                $client->quit();
            } catch (Throwable) {
                // Ignore cleanup errors
            }
        }

        $this->clients = [];

        if ($this->process instanceof Process && $this->process->isRunning()) {
            $this->process->stop();
        }

        $this->process = null;

        // Last resort — kill by PID if the process is still alive
        $this->killByPid();

        // Remove from the global registry
        self::$instances = array_filter(
            self::$instances,
            fn (self $instance): bool => $instance !== $this,
        );
    }

    private function findBinary(): string
    {
        $binary = new ExecutableFinder()->find('chromedriver', null, ['./drivers']);

        if ($binary === null) {
            throw new RuntimeException(
                '"chromedriver" binary not found. Run "composer run driver:install" to install it.',
            );
        }

        return $binary;
    }

    private function killByPid(): void
    {
        if ($this->pid === null) {
            return;
        }

        // Check if the process is still running and kill it
        if (PHP_OS_FAMILY === 'Windows') {
            @exec(sprintf('taskkill /F /PID %d 2>NUL', $this->pid));
        } else {
            @exec(sprintf('kill -9 %d 2>/dev/null', $this->pid));
        }

        $this->pid = null;
    }

    private function registerForCleanup(): void
    {
        self::$instances[] = $this;

        if (!self::$shutdownRegistered) {
            self::$shutdownRegistered = true;

            register_shutdown_function(static function (): void {
                self::cleanup();
            });

            // Handle SIGINT (Ctrl+C) and SIGTERM gracefully
            if (function_exists('pcntl_signal')) {
                pcntl_signal(SIGINT, static function (): void {
                    self::cleanup();
                    exit(130);
                });

                pcntl_signal(SIGTERM, static function (): void {
                    self::cleanup();
                    exit(143);
                });
            }
        }
    }

    private function waitUntilReady(): void
    {
        $timeout  = 10;
        $deadline = time() + $timeout;

        while (time() < $deadline) {
            if ($this->process instanceof Process && !$this->process->isRunning()) {
                throw new RuntimeException('ChromeDriver process died: ' . $this->process->getErrorOutput());
            }

            $socket = @stream_socket_client(
                sprintf('tcp://%s:%d', $this->host, $this->port),
                $errno,
                $errstr,
                timeout: max(1, $deadline - time()),
            );

            if ($socket !== false) {
                fclose($socket);

                return;
            }
        }

        throw new RuntimeException('ChromeDriver did not start within 10 seconds.');
    }
}
