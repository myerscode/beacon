<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Client;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Symfony\Component\Panther\Client;

/**
 * ClientFactory that connects to an already-running ChromeDriver instance.
 * Does not manage the ChromeDriver lifecycle — assumes it's externally managed.
 */
class RemoteClientFactory implements ClientFactory
{
    /**
     * @param string   $url             The ChromeDriver URL (e.g. http://127.0.0.1:9515)
     * @param string[] $chromeArguments  Chrome arguments for new sessions
     */
    public function __construct(
        private readonly string $url,
        private readonly array $chromeArguments = ['--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage'],
    ) {
    }

    public function create(): ClientInterface
    {
        $capabilities = DesiredCapabilities::chrome();

        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments($this->chromeArguments);

        $capabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);

        $client = Client::createSeleniumClient($this->url, $capabilities);

        return new ClientAdapter($client);
    }
}
