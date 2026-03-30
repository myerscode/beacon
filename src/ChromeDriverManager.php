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
     * @var Client[]
     */
    private array $clients = [];

    private string $host = '127.0.0.1';

    private readonly int $port;
    private ?Process $process = null;

    /**
     * @param string[] $chromeArguments
     */
    public function __construct(
        private readonly array $chromeArguments = ['--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage'],
        ?string $chromeDriverBinary = null,
    ) {
        $this->port            = random_int(10000, 60000);

        $binary = $chromeDriverBinary ?? $this->findBinary();

        $this->process = new Process([$binary, '--port=' . $this->port], null, null, null, null);
        $this->process->start();

        $this->waitUntilReady();
    }

    public function __destruct()
    {
        $this->quit();
    }

    /**
     * Create a new Panther Client session on this driver.
     */
    public function createClient(): Client
    {
        $url          = sprintf('http://%s:%d', $this->host, $this->port);
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
            $this->process = null;
        }
    }

    private function findBinary(): string
    {
        $binary = new ExecutableFinder()->find('chromedriver', null, ['./drivers']);

        if ($binary === null) {
            throw new RuntimeException(
                '"chromedriver" binary not found. Run "vendor/bin/bdi detect drivers" to install it.',
            );
        }

        return $binary;
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
