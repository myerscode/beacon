<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Lighthouse;

use JsonException;
use Myerscode\Beacon\Lighthouse\LighthouseResult;
use Myerscode\Beacon\Lighthouse\LighthouseRunner;
use Myerscode\Beacon\ProcessFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

final class LighthouseRunnerTest extends TestCase
{
    /**
     * @return array<string, array{callable, callable}>
     */
    public static function commandConstructionProvider(): array
    {
        return [
            'mobile defaults' => [
                fn (LighthouseRunner $r) => $r,
                function (array $command, ?array $env): void {
                    self::assertSame('lighthouse', $command[0]);
                    self::assertSame('https://example.com', $command[1]);
                    self::assertContains('--output=json', $command);
                    self::assertContains('--form-factor=mobile', $command);
                    self::assertNotContains('--preset=desktop', $command);
                    self::assertNull($env);
                },
            ],
            'desktop adds preset' => [
                fn (LighthouseRunner $r) => $r->desktop(),
                function (array $command, ?array $env): void {
                    self::assertContains('--form-factor=desktop', $command);
                    self::assertContains('--preset=desktop', $command);
                },
            ],
            'custom binary' => [
                fn (LighthouseRunner $r) => $r->lighthouseBinary('/custom/lighthouse'),
                function (array $command, ?array $env): void {
                    self::assertSame('/custom/lighthouse', $command[0]);
                },
            ],
            'chrome path sets env' => [
                fn (LighthouseRunner $r) => $r->chromePath('/usr/bin/chrome'),
                function (array $command, ?array $env): void {
                    self::assertNotNull($env);
                    self::assertSame('/usr/bin/chrome', $env['CHROME_PATH']);
                },
            ],
            'default chrome flags included' => [
                fn (LighthouseRunner $r) => $r,
                function (array $command, ?array $env): void {
                    $flagArgs = array_filter($command, fn ($c) => str_starts_with($c, '--chrome-flags='));
                    self::assertNotEmpty($flagArgs);
                    self::assertStringContainsString('--headless', array_values($flagArgs)[0]);
                },
            ],
            'empty chrome flags omits argument' => [
                fn (LighthouseRunner $r) => $r->chromeFlags([]),
                function (array $command, ?array $env): void {
                    $flagArgs = array_filter($command, fn ($c) => str_starts_with($c, '--chrome-flags='));
                    self::assertEmpty($flagArgs);
                },
            ],
        ];
    }

    // =========================================================================
    // Fluent API
    // =========================================================================

    public function testFluentApiReturnsSelf(): void
    {
        $runner = new LighthouseRunner();

        $this->assertSame($runner, $runner->chromeFlags(['--headless']));
        $this->assertSame($runner, $runner->chromePath('/usr/bin/chrome'));
        $this->assertSame($runner, $runner->desktop());
        $this->assertSame($runner, $runner->mobile());
        $this->assertSame($runner, $runner->formFactor('mobile'));
        $this->assertSame($runner, $runner->lighthouseBinary('/usr/bin/lighthouse'));
        $this->assertSame($runner, $runner->timeout(30));
    }
    // =========================================================================
    // run() — command construction
    // =========================================================================

    #[DataProvider('commandConstructionProvider')]
    public function testRunBuildsCorrectCommand(callable $configure, callable $assertions): void
    {
        $json = json_encode(['categories' => [], 'audits' => []]);

        $process = $this->createStub(Process::class);
        $process->method('isSuccessful')->willReturn(true);
        $process->method('getOutput')->willReturn($json);

        $factory = $this->createMock(ProcessFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->willReturnCallback(function (array $command, ?array $env) use ($process, $assertions): Process {
                $assertions($command, $env);

                return $process;
            });

        $runner = $configure(new LighthouseRunner(processFactory: $factory));
        $result = $runner->run('https://example.com');

        $this->assertInstanceOf(LighthouseResult::class, $result);
    }

    // =========================================================================
    // run() — timeout
    // =========================================================================

    public function testRunSetsConfiguredTimeout(): void
    {
        $json = json_encode(['categories' => [], 'audits' => []]);

        $process = $this->createMock(Process::class);
        $process->expects($this->once())->method('setTimeout')->with(60);
        $process->method('isSuccessful')->willReturn(true);
        $process->method('getOutput')->willReturn($json);

        $factory = $this->createStub(ProcessFactory::class);
        $factory->method('create')->willReturn($process);

        $runner = new LighthouseRunner(processFactory: $factory);
        $runner->timeout(60)->run('https://example.com');
    }

    public function testRunThrowsWhenOutputIsInvalidJson(): void
    {
        $this->expectException(JsonException::class);

        $process = $this->createStub(Process::class);
        $process->method('isSuccessful')->willReturn(true);
        $process->method('getOutput')->willReturn('not json');

        $factory = $this->createStub(ProcessFactory::class);
        $factory->method('create')->willReturn($process);

        (new LighthouseRunner(processFactory: $factory))->run('https://example.com');
    }

    // =========================================================================
    // run() — failure paths
    // =========================================================================

    public function testRunThrowsWhenProcessFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lighthouse failed');

        $process = $this->createStub(Process::class);
        $process->method('isSuccessful')->willReturn(false);
        $process->method('getErrorOutput')->willReturn('Connection refused');

        $factory = $this->createStub(ProcessFactory::class);
        $factory->method('create')->willReturn($process);

        (new LighthouseRunner(processFactory: $factory))->run('https://example.com');
    }
}
