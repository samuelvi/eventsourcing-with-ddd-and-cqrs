<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Currency;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testBuildsMoneyFromAmountAndCurrency(): void
    {
        $money = Money::fromFloat(120.25, Currency::fromString('usd'));

        self::assertSame(120.25, $money->amount()->toFloat());
        self::assertSame('USD', $money->currency()->toString());
    }
}
