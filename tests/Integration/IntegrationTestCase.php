<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use RuntimeException;

abstract class IntegrationTestCase extends TestCase
{
    protected static int $port = 0;
    protected static ?Process $server = null;

    public static function setUpBeforeClass(): void
    {
        self::$port   = random_int(8100, 8999);
        $docRoot      = __DIR__ . '/../fixtures';

        self::$server = new Process([
            PHP_BINARY,
            '-S',
            sprintf('127.0.0.1:%d', self::$port),
            '-t',
            $docRoot,
        ]);

        self::$server->start();

        // Wait for server to be ready
        $deadline = time() + 5;

        while (time() < $deadline) {
            $socket = @stream_socket_client(
                sprintf('tcp://127.0.0.1:%d', self::$port),
                $errno,
                $errstr,
                timeout: 1,
            );

            if ($socket !== false) {
                fclose($socket);

                return;
            }
        }

        throw new RuntimeException('Test server did not start');
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;
    }

    protected static function baseUrl(): string
    {
        return sprintf('http://127.0.0.1:%d', self::$port);
    }
}
