<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Support;

use Myerscode\Beacon\Support\DependencyCheck;
use Myerscode\Beacon\Support\DependencyChecker;
use PHPUnit\Framework\TestCase;

final class DependencyCheckerTest extends TestCase
{
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

    public function testEachCheckHasMessage(): void
    {
        $results = (new DependencyChecker())->check();

        foreach ($results as $result) {
            $this->assertNotEmpty($result->message);
        }
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
}
