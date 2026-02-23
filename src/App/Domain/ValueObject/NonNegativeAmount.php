<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class NonNegativeAmount
{
    private function __construct(
        private float $value,
    ) {}

    public static function fromFloat(float $value): self
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative.');
        }

        return new self($value);
    }

    public function toFloat(): float
    {
        return $this->value;
    }
}
