<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Support;

use Myerscode\Beacon\Support\DependencyCheck;
use Myerscode\Beacon\Support\DependencyChecker;
use PHPUnit\Framework\TestCase;

class TestableDependencyChecker extends DependencyChecker
{
    public function callVersionCompatibility(DependencyCheck $chrome, DependencyCheck $chromeDriver): ?DependencyCheck
    {
        return $this->versionCompatibility($chrome, $chromeDriver);
    }
}

final class DependencyCheckerTest extends TestCase
{
    // =========================================================================
    // check()
    // =========================================================================

    public function testCheckContainsExpectedNames(): void
    {
        $results = (new DependencyChecker())->check();
        $names   = array_map(fn (DependencyCheck $dependencyCheck): string => $dependencyCheck->name, $results);

        $this->assertContains('Chrome/Chromium', $names);
        $this->assertContains('ChromeDriver', $names);
        $this->assertContains('Node.js', $names);
        $this->assertContains('Lighthouse', $names);
    }

    public function testCheckReturnsArrayOfChecks(): void
    {
        $results = (new DependencyChecker())->check();

        $this->assertGreaterThanOrEqual(4, count($results));
        $this->assertContainsOnlyInstancesOf(DependencyCheck::class, $results);
    }

    public function testEachCheckHasMessage(): void
    {
        $results = (new DependencyChecker())->check();

        foreach ($results as $result) {
            $this->assertNotEmpty($result->message);
        }
    }

    // =========================================================================
    // Individual dependency checks
    // =========================================================================

    public function testChromeCheckReturnsDependencyCheck(): void
    {
        $dependencyCheck = (new DependencyChecker())->chrome();

        $this->assertInstanceOf(DependencyCheck::class, $dependencyCheck);
        $this->assertSame('Chrome/Chromium', $dependencyCheck->name);
    }

    public function testChromeDriverCheckReturnsDependencyCheck(): void
    {
        $dependencyCheck = (new DependencyChecker())->chromeDriver();

        $this->assertInstanceOf(DependencyCheck::class, $dependencyCheck);
        $this->assertSame('ChromeDriver', $dependencyCheck->name);
    }

    public function testLighthouseCheckReturnsDependencyCheck(): void
    {
        $dependencyCheck = (new DependencyChecker())->lighthouse();

        $this->assertInstanceOf(DependencyCheck::class, $dependencyCheck);
        $this->assertSame('Lighthouse', $dependencyCheck->name);
    }

    public function testNodeCheckReturnsDependencyCheck(): void
    {
        $dependencyCheck = (new DependencyChecker())->node();

        $this->assertInstanceOf(DependencyCheck::class, $dependencyCheck);
        $this->assertSame('Node.js', $dependencyCheck->name);
    }

    // =========================================================================
    // versionCompatibility
    // =========================================================================

    public function testMatchingVersionsReturnOk(): void
    {
        $chrome = new DependencyCheck('Chrome', true, '/usr/bin/chrome', '120.0.6099.109', 'Found');
        $driver = new DependencyCheck('ChromeDriver', true, '/usr/bin/chromedriver', '120.0.6099.109', 'Found');

        $result = (new TestableDependencyChecker())->callVersionCompatibility($chrome, $driver);

        $this->assertNotNull($result);
        $this->assertTrue($result->ok());
        $this->assertStringContainsString('120', $result->message);
    }

    public function testMismatchedVersionsReturnFail(): void
    {
        $chrome = new DependencyCheck('Chrome', true, '/usr/bin/chrome', '120.0.6099.109', 'Found');
        $driver = new DependencyCheck('ChromeDriver', true, '/usr/bin/chromedriver', '121.0.6100.0', 'Found');

        $result = (new TestableDependencyChecker())->callVersionCompatibility($chrome, $driver);

        $this->assertNotNull($result);
        $this->assertFalse($result->ok());
        $this->assertStringContainsString('differ', $result->message);
    }

    public function testVersionCompatibilityReturnsNullWhenChromeNotFound(): void
    {
        $chrome = new DependencyCheck('Chrome', false, null, null, 'Not found');
        $driver = new DependencyCheck('ChromeDriver', true, '/usr/bin/chromedriver', '120.0', 'Found');

        $this->assertNull((new TestableDependencyChecker())->callVersionCompatibility($chrome, $driver));
    }

    public function testVersionCompatibilityReturnsNullWhenChromeVersionNull(): void
    {
        $chrome = new DependencyCheck('Chrome', true, '/usr/bin/chrome', null, 'Found');
        $driver = new DependencyCheck('ChromeDriver', true, '/usr/bin/chromedriver', '120.0', 'Found');

        $this->assertNull((new TestableDependencyChecker())->callVersionCompatibility($chrome, $driver));
    }

    public function testVersionCompatibilityReturnsNullWhenDriverNotFound(): void
    {
        $chrome = new DependencyCheck('Chrome', true, '/usr/bin/chrome', '120.0', 'Found');
        $driver = new DependencyCheck('ChromeDriver', false, null, null, 'Not found');

        $this->assertNull((new TestableDependencyChecker())->callVersionCompatibility($chrome, $driver));
    }

    public function testVersionCompatibilityReturnsNullWhenDriverVersionNull(): void
    {
        $chrome = new DependencyCheck('Chrome', true, '/usr/bin/chrome', '120.0', 'Found');
        $driver = new DependencyCheck('ChromeDriver', true, '/usr/bin/chromedriver', null, 'Found');

        $this->assertNull((new TestableDependencyChecker())->callVersionCompatibility($chrome, $driver));
    }
}
