<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class PositiveInt
{
    private function __construct(
        private int $value,
    ) {}

    public static function fromInt(int $value): self
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException('Value must be a positive integer.');
        }

        return new self($value);
    }

    public function toInt(): int
    {
        return $this->value;
    }
}
