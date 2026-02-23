<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\NonNegativeAmount;
use PHPUnit\Framework\TestCase;

final class NonNegativeAmountTest extends TestCase
{
    public function testAcceptsZeroAndPositiveValues(): void
    {
        self::assertSame(0.0, NonNegativeAmount::fromFloat(0.0)->toFloat());
        self::assertSame(99.5, NonNegativeAmount::fromFloat(99.5)->toFloat());
    }

    public function testRejectsNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NonNegativeAmount::fromFloat(-1.0);
    }
}
