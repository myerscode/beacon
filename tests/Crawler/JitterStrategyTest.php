<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Crawler;

use Myerscode\Beacon\Crawler\JitterStrategy;
use PHPUnit\Framework\TestCase;

final class JitterStrategyTest extends TestCase
{
    public function testFullStrategyHasCorrectValue(): void
    {
        $this->assertSame('full', JitterStrategy::FULL->value);
    }

    public function testNoneStrategyHasCorrectValue(): void
    {
        $this->assertSame('none', JitterStrategy::NONE->value);
    }

    public function testStrategyCanBeCreatedFromString(): void
    {
        $this->assertSame(JitterStrategy::FULL, JitterStrategy::from('full'));
        $this->assertSame(JitterStrategy::NONE, JitterStrategy::from('none'));
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(JitterStrategy::tryFrom('invalid'));
    }
}
