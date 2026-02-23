<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Currency;
use PHPUnit\Framework\TestCase;

final class CurrencyTest extends TestCase
{
    public function testNormalizesCurrencyToUppercase(): void
    {
        $currency = Currency::fromString(' eur ');

        self::assertSame('EUR', $currency->toString());
    }

    public function testRejectsInvalidCurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Currency::fromString('EURO');
    }
}
