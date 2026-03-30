<?php

declare(strict_types=1);

namespace Myerscode\Beacon;

class Browser
{
    /**
     * @var string[]
     */
    private array $arguments = [
        '--headless=new',
        '--disable-gpu',
        '--no-sandbox',
        '--disable-dev-shm-usage',
    ];

    private ?string $chromeDriverBinary = null;
    private ?ChromeDriverManager $chromeDriverManager = null;

    private ?ClientInterface $client = null;

    private int $waitTimeout = 10;

    public function __destruct()
    {
        $this->quit();
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * Add a custom Chrome argument.
     */
    public function addArgument(string $argument): self
    {
        $this->arguments[] = $argument;

        return $this;
    }

    /**
     * Set a custom Chrome binary path.
     */
    public function chromeBinary(string $path): self
    {
        $this->arguments[] = '--chrome-binary=' . $path;

        return $this;
    }

    /**
     * Set a custom ChromeDriver binary path.
     */
    public function chromeDriverBinary(string $path): self
    {
        $this->chromeDriverBinary = $path;

        return $this;
    }

    /**
     * Get the shared ChromeDriver manager.
     * Starts the driver if not already running.
     */
    public function getDriver(): ChromeDriverManager
    {
        if ($this->chromeDriverManager === null) {
            $this->chromeDriverManager = new ChromeDriverManager($this->arguments, $this->chromeDriverBinary);
        }

        return $this->chromeDriverManager;
    }

    /**
     * Close the browser and shut down ChromeDriver.
     */
    public function quit(): void
    {
        $this->client = null;

        if ($this->chromeDriverManager !== null) {
            $this->chromeDriverManager->quit();
            $this->chromeDriverManager = null;
        }
    }

    /**
     * Navigate to a URL and return a Page instance for fluent interaction.
     */
    public function visit(string $url): Page
    {
        $client = $this->getAdapter();
        $client->request('GET', $url);
        $client->waitForPageReady($this->waitTimeout);

        return new Page($client, $url, $this);
    }

    /**
     * Set the timeout for waiting on page loads (seconds).
     */
    public function waitTimeout(int $seconds): self
    {
        $this->waitTimeout = $seconds;

        return $this;
    }

    /**
     * Set the browser window size.
     */
    public function windowSize(int $width, int $height): self
    {
        $this->arguments[] = sprintf('--window-size=%d,%d', $width, $height);

        return $this;
    }

    private function getAdapter(): ClientInterface
    {
        if ($this->client === null) {
            $client        = $this->getDriver()->createClient();
            $this->client = new ClientAdapter($client);
        }

        return $this->client;
    }
}
