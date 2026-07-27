<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Driver;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use RuntimeException;
use Symfony\Component\Panther\Client;
use Symfony\Component\Process\Process;
use Throwable;

class ChromeDriverManager
{
    private const MAX_PORT_ATTEMPTS = 20;

    private const PORT_MAX = 60000;

    private const PORT_MIN = 10000;

    /**
     * @var self[]
     */
    private static array $instances = [];

    private static bool $shutdownRegistered = false;

    /**
     * Ports already allocated by this process to avoid collisions
     * between concurrent ChromeDriverManager instances.
     *
     * @var int[]
     */
    private static array $usedPorts = [];

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
        ?BinaryFinder $binaryFinder = null,
        ?ProcessFactory $processFactory = null,
        private readonly bool $verbose = false,
        private readonly int $portMin = self::PORT_MIN,
        private readonly int $portMax = self::PORT_MAX,
    ) {
        $this->port = $this->findAvailablePort();

        $binary = $chromeDriverBinary ?? ($binaryFinder ?? new BinaryFinder())->find();

        $command = [$binary, '--port='.$this->port];

        if ($this->verbose) {
            $command[] = '--verbose';
            $command[] = '--readable-timestamp';
        }

        $factory = $processFactory ?? new ProcessFactory();
        $this->process = $factory->create($command);
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
        $url = sprintf('http://%s:%d', $this->host, $this->port);
        $desiredCapabilities = DesiredCapabilities::chrome();

        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments($this->chromeArguments);

        $desiredCapabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);

        $client = Client::createSeleniumClient($url, $desiredCapabilities);

        $this->clients[] = $client;

        return $client;
    }

    /**
     * Get incremental output from the ChromeDriver process (stdout + stderr).
     */
    public function getIncrementalOutput(): string
    {
        if ($this->process === null) {
            return '';
        }

        return $this->process->getIncrementalOutput().$this->process->getIncrementalErrorOutput();
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
     * Check if the ChromeDriver process is still running.
     */
    public function isRunning(): bool
    {
        return $this->process instanceof Process && $this->process->isRunning();
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

    /**
     * Find a port that is available for binding.
     *
     * Generates random ports within the configured range, skips any already
     * claimed by this process, and verifies availability with a TCP bind test.
     *
     * @throws RuntimeException If no available port is found within max attempts.
     */
    private function findAvailablePort(): int
    {
        for ($attempt = 0; $attempt < self::MAX_PORT_ATTEMPTS; $attempt++) {
            $port = random_int($this->portMin, $this->portMax);

            if (in_array($port, self::$usedPorts, true)) {
                continue;
            }

            if ($this->isPortAvailable($port)) {
                self::$usedPorts[] = $port;

                return $port;
            }
        }

        throw new RuntimeException(
            sprintf('Could not find an available port after %d attempts (range %d–%d).', self::MAX_PORT_ATTEMPTS, $this->portMin, $this->portMax),
        );
    }

    private function isPortAvailable(int $port): bool
    {
        $socket = @stream_socket_server(
            sprintf('tcp://%s:%d', $this->host, $port),
            $errno,
            $errstr,
        );

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    private function killByPid(): void
    {
        if ($this->pid === null) {
            return;
        }

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

        if (! self::$shutdownRegistered) {
            self::$shutdownRegistered = true;

            register_shutdown_function(static function (): void {
                self::cleanup();
            });

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
        $timeout = 10;
        $deadline = time() + $timeout;

        while (time() < $deadline) {
            if ($this->process instanceof Process && ! $this->process->isRunning()) {
                throw new RuntimeException('ChromeDriver process died: '.$this->process->getErrorOutput());
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
