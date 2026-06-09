<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Driver;

use Myerscode\Beacon\Driver\BinaryFinder;
use Myerscode\Beacon\Driver\ChromeDriverManager;
use Myerscode\Beacon\Driver\ProcessFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class ChromeDriverManagerTest extends TestCase
{
    private ?ChromeDriverManager $manager = null;

    protected function tearDown(): void
    {
        $this->manager?->quit();
        $this->manager = null;
    }

    // =========================================================================
    // BinaryFinder
    // =========================================================================

    public function testBinaryFinderLocatesDriver(): void
    {
        $localBinary = __DIR__ . '/../../drivers/chromedriver';

        if (!file_exists($localBinary) || !is_executable($localBinary)) {
            $this->markTestSkipped('Local chromedriver binary not available');
        }

        $finder = new BinaryFinder();
        $result = $finder->find();

        $this->assertFileExists($result);
    }

    public function testBinaryFinderThrowsWhenDriverNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"chromedriver" binary not found');

        $driversDir = __DIR__ . '/../../drivers';
        $tempDir = __DIR__ . '/../../drivers_backup_test';
        $renamed = false;

        if (is_dir($driversDir)) {
            rename($driversDir, $tempDir);
            $renamed = true;
        }

        $originalPath = getenv('PATH');
        putenv('PATH=/nonexistent');

        try {
            (new BinaryFinder())->find();
        } finally {
            putenv('PATH=' . $originalPath);

            if ($renamed) {
                rename($tempDir, $driversDir);
            }
        }
    }

    public function testCleanupQuitsAllActiveInstances(): void
    {
        $binary = $this->findChromeDriverBinary();

        if ($binary === null) {
            $this->markTestSkipped('chromedriver binary not available');
        }

        $a = new ChromeDriverManager(
            chromeArguments: ['--headless=new', '--disable-gpu', '--no-sandbox'],
            chromeDriverBinary: $binary,
        );

        $b = new ChromeDriverManager(
            chromeArguments: ['--headless=new', '--disable-gpu', '--no-sandbox'],
            chromeDriverBinary: $binary,
        );

        $portA = $a->getPort();
        $portB = $b->getPort();

        ChromeDriverManager::cleanup();

        usleep(200000);

        $socketA = @stream_socket_client("tcp://127.0.0.1:{$portA}", $errno, $errstr, timeout: 1);
        $socketB = @stream_socket_client("tcp://127.0.0.1:{$portB}", $errno, $errstr, timeout: 1);

        $this->assertFalse($socketA);
        $this->assertFalse($socketB);
    }

    public function testConstructorSkipsBinaryFinderWhenBinaryProvided(): void
    {
        $binary = $this->findChromeDriverBinary();

        if ($binary === null) {
            $this->markTestSkipped('chromedriver binary not available');
        }

        $binaryFinder = $this->createMock(BinaryFinder::class);
        $binaryFinder->expects($this->never())->method('find');

        $this->manager = new ChromeDriverManager(
            chromeArguments: ['--headless=new', '--disable-gpu', '--no-sandbox'],
            chromeDriverBinary: $binary,
            binaryFinder: $binaryFinder,
        );

        $this->assertGreaterThanOrEqual(10000, $this->manager->getPort());
    }

    // =========================================================================
    // Constructor — error paths
    // =========================================================================

    public function testConstructorThrowsWhenBinaryFinderFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"chromedriver" binary not found');

        $binaryFinder = $this->createStub(BinaryFinder::class);
        $binaryFinder->method('find')
            ->willThrowException(new RuntimeException('"chromedriver" binary not found.'));

        $processFactory = $this->createMock(ProcessFactory::class);
        $processFactory->expects($this->never())->method('create');

        new ChromeDriverManager(
            binaryFinder: $binaryFinder,
            processFactory: $processFactory,
        );
    }

    public function testConstructorThrowsWhenProcessDiesImmediately(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ChromeDriver process died');

        $process = $this->createStub(Process::class);
        $process->method('start');
        $process->method('getPid')->willReturn(99999);
        $process->method('isRunning')->willReturn(false);
        $process->method('getErrorOutput')->willReturn('Cannot bind to port');

        $processFactory = $this->createStub(ProcessFactory::class);
        $processFactory->method('create')->willReturn($process);

        new ChromeDriverManager(
            chromeDriverBinary: '/usr/bin/true',
            processFactory: $processFactory,
        );
    }

    // =========================================================================
    // Constructor — dependency injection
    // =========================================================================

    public function testConstructorUsesBinaryFinderWhenNoBinaryProvided(): void
    {
        $binary = $this->findChromeDriverBinary();

        if ($binary === null) {
            $this->markTestSkipped('chromedriver binary not available');
        }

        $binaryFinder = $this->createMock(BinaryFinder::class);
        $binaryFinder->expects($this->once())->method('find')->willReturn($binary);

        $this->manager = new ChromeDriverManager(
            chromeArguments: ['--headless=new', '--disable-gpu', '--no-sandbox'],
            binaryFinder: $binaryFinder,
        );

        $this->assertGreaterThanOrEqual(10000, $this->manager->getPort());
    }

    public function testConstructorUsesProcessFactory(): void
    {
        $binary = $this->findChromeDriverBinary();

        if ($binary === null) {
            $this->markTestSkipped('chromedriver binary not available');
        }

        $processFactory = $this->createMock(ProcessFactory::class);
        $processFactory->expects($this->once())
            ->method('create')
            ->willReturnCallback(function (array $command) use ($binary): Process {
                $this->assertSame($binary, $command[0]);
                $this->assertStringStartsWith('--port=', $command[1]);

                return new Process($command, null, null, null, null);
            });

        $this->manager = new ChromeDriverManager(
            chromeArguments: ['--headless=new', '--disable-gpu', '--no-sandbox'],
            chromeDriverBinary: $binary,
            processFactory: $processFactory,
        );

        $this->assertGreaterThanOrEqual(10000, $this->manager->getPort());
    }

    // =========================================================================
    // Public API — getPort(), getUrl(), quit(), cleanup()
    // =========================================================================

    public function testGetPortAndUrlReturnConsistentValues(): void
    {
        $binary = $this->findChromeDriverBinary();

        if ($binary === null) {
            $this->markTestSkipped('chromedriver binary not available');
        }

        $this->manager = new ChromeDriverManager(
            chromeArguments: ['--headless=new', '--disable-gpu', '--no-sandbox'],
            chromeDriverBinary: $binary,
        );

        $port = $this->manager->getPort();
        $url = $this->manager->getUrl();

        $this->assertGreaterThanOrEqual(10000, $port);
        $this->assertLessThanOrEqual(60000, $port);
        $this->assertSame("http://127.0.0.1:{$port}", $url);
    }

    // =========================================================================
    // ProcessFactory — standalone
    // =========================================================================

    public function testProcessFactoryCreatesProcess(): void
    {
        $factory = new ProcessFactory();
        $process = $factory->create(['echo', 'hello']);

        $this->assertInstanceOf(Process::class, $process);
    }

    public function testQuitStopsDriverAndIsIdempotent(): void
    {
        $binary = $this->findChromeDriverBinary();

        if ($binary === null) {
            $this->markTestSkipped('chromedriver binary not available');
        }

        $this->manager = new ChromeDriverManager(
            chromeArguments: ['--headless=new', '--disable-gpu', '--no-sandbox'],
            chromeDriverBinary: $binary,
        );

        $port = $this->manager->getPort();

        // Port reachable before quit
        $socket = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, timeout: 2);
        $this->assertNotFalse($socket);
        fclose($socket);

        // First quit stops the process
        $this->manager->quit();

        usleep(200000);

        $socket = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, timeout: 1);
        $this->assertFalse($socket, 'Port should not be reachable after quit');

        // Second quit doesn't throw
        $this->manager->quit();
        $this->manager = null;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function findChromeDriverBinary(): ?string
    {
        $localBinary = __DIR__ . '/../../drivers/chromedriver';

        if (file_exists($localBinary) && is_executable($localBinary)) {
            return $localBinary;
        }

        return (new ExecutableFinder())->find('chromedriver');
    }
}
