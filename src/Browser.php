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

    /**
     * Force cleanup of all active ChromeDriver instances.
     */
    public static function cleanup(): void
    {
        ChromeDriverManager::cleanup();
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
     * Visit multiple URLs concurrently and return a Page for each.
     *
     * Each URL gets its own Chrome session on the shared ChromeDriver process,
     * so all returned Page objects are independently usable.
     * Concurrency controls how many sessions load pages in parallel.
     *
     * @param string[] $urls
     * @param int      $concurrency Max number of Chrome sessions to run in parallel
     * @return Page[]
     */
    public function visitAll(array $urls, int $concurrency = 5): array
    {
        if ($urls === []) {
            return [];
        }

        $driver  = $this->getDriver();
        $timeout = $this->waitTimeout;

        // Each URL gets its own client so Pages are independent
        $urlClients = [];

        foreach ($urls as $index => $url) {
            $urlClients[$index] = new ClientAdapter($driver->createClient());
        }

        /** @var array<int, Page|null> $results indexed by original URL position */
        $results = array_fill(0, count($urls), null);

        /** @var array<int, array{index: int, url: string}> $queue */
        $queue = [];

        foreach ($urls as $index => $url) {
            $queue[] = ['index' => $index, 'url' => $url];
        }

        $slotCount = min($concurrency, count($urls));

        /** @var array<int, \Fiber> $fibers keyed by slot */
        $fibers = [];

        /** @var array<int, int> $fiberIndex maps slot to original URL index */
        $fiberIndex = [];

        /** @var array<int, bool> $available */
        $available = array_fill(0, $slotCount, true);

        while ($queue !== [] || $fibers !== []) {
            // Fill available slots
            foreach ($available as $slot => $free) {
                if (!$free || $queue === []) {
                    continue;
                }

                $item   = array_shift($queue);
                $client = $urlClients[$item['index']];

                $available[$slot]  = false;
                $fiberIndex[$slot] = $item['index'];

                $fibers[$slot] = new \Fiber(static function () use ($client, $item, $timeout): Page {
                    $client->request('GET', $item['url']);
                    $client->waitForPageReady($timeout);

                    return new Page($client, $item['url']);
                });

                try {
                    $fibers[$slot]->start();
                } catch (\Throwable) {
                    $available[$slot] = true;
                    unset($fibers[$slot], $fiberIndex[$slot]);
                }
            }

            // Tick active fibers
            foreach ($fibers as $slot => $fiber) {
                try {
                    if ($fiber->isTerminated()) {
                        $results[$fiberIndex[$slot]] = $fiber->getReturn();
                        $available[$slot]            = true;
                        unset($fibers[$slot], $fiberIndex[$slot]);
                    } elseif ($fiber->isSuspended()) {
                        $fiber->resume();
                    }
                } catch (\Throwable) {
                    $available[$slot] = true;
                    unset($fibers[$slot], $fiberIndex[$slot]);
                }
            }
        }

        return array_values(array_filter($results));
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
