<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\PositiveInt;
use PHPUnit\Framework\TestCase;

final class PositiveIntTest extends TestCase
{
    public function testAcceptsPositiveInt(): void
    {
        self::assertSame(3, PositiveInt::fromInt(3)->toInt());
    }

    public function testRejectsZeroOrNegativeValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PositiveInt::fromInt(0);
    }
}
