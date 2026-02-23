<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class Money
{
    private function __construct(
        private NonNegativeAmount $amount,
        private Currency $currency,
    ) {}

    public static function fromFloat(float $amount, Currency $currency): self
    {
        return new self(NonNegativeAmount::fromFloat($amount), $currency);
    }

    public function amount(): NonNegativeAmount
    {
        return $this->amount;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }
}
