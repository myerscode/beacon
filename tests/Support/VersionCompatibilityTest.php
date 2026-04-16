<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Support;

use Myerscode\Beacon\Support\DependencyCheck;
use Myerscode\Beacon\Support\DependencyChecker;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class VersionCompatibilityTest extends TestCase
{
    public function testMatchingVersionsReturnOk(): void
    {
        $chrome = new DependencyCheck('Chrome', true, '/usr/bin/chrome', '120.0.6099.109', 'Found');
        $driver = new DependencyCheck('ChromeDriver', true, '/usr/bin/chromedriver', '120.0.6099.109', 'Found');

        $result = $this->checkCompatibility($chrome, $driver);

        $this->assertNotNull($result);
        $this->assertTrue($result->ok());
        $this->assertStringContainsString('120', $result->message);
    }

    public function testMismatchedVersionsReturnFail(): void
    {
        $chrome = new DependencyCheck('Chrome', true, '/usr/bin/chrome', '120.0.6099.109', 'Found');
        $driver = new DependencyCheck('ChromeDriver', true, '/usr/bin/chromedriver', '121.0.6100.0', 'Found');

        $result = $this->checkCompatibility($chrome, $driver);

        $this->assertNotNull($result);
        $this->assertFalse($result->ok());
        $this->assertStringContainsString('differ', $result->message);
    }

    public function testReturnsNullWhenChromeNotFound(): void
    {
        $chrome = new DependencyCheck('Chrome', false, null, null, 'Not found');
        $driver = new DependencyCheck('ChromeDriver', true, '/usr/bin/chromedriver', '120.0', 'Found');

        $result = $this->checkCompatibility($chrome, $driver);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenChromeVersionNull(): void
    {
        $chrome = new DependencyCheck('Chrome', true, '/usr/bin/chrome', null, 'Found');
        $driver = new DependencyCheck('ChromeDriver', true, '/usr/bin/chromedriver', '120.0', 'Found');

        $result = $this->checkCompatibility($chrome, $driver);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenDriverNotFound(): void
    {
        $chrome = new DependencyCheck('Chrome', true, '/usr/bin/chrome', '120.0', 'Found');
        $driver = new DependencyCheck('ChromeDriver', false, null, null, 'Not found');

        $result = $this->checkCompatibility($chrome, $driver);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenDriverVersionNull(): void
    {
        $chrome = new DependencyCheck('Chrome', true, '/usr/bin/chrome', '120.0', 'Found');
        $driver = new DependencyCheck('ChromeDriver', true, '/usr/bin/chromedriver', null, 'Found');

        $result = $this->checkCompatibility($chrome, $driver);

        $this->assertNull($result);
    }
    private function checkCompatibility(DependencyCheck $chrome, DependencyCheck $driver): ?DependencyCheck
    {
        $method = new ReflectionMethod(DependencyChecker::class, 'versionCompatibility');

        return $method->invoke(new DependencyChecker(), $chrome, $driver);
    }
}
