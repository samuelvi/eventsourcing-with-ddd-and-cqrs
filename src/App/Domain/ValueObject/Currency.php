<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class Currency
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));
        if (!preg_match('/^[A-Z]{3}$/', $normalized)) {
            throw new \InvalidArgumentException('Currency must be a valid 3-letter ISO code.');
        }

        return new self($normalized);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
