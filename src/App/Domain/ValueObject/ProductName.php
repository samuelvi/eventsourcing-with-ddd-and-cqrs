<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class ProductName
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $normalized = trim($value);
        if ($normalized === '') {
            throw new \InvalidArgumentException('Product name cannot be empty.');
        }

        $length = mb_strlen($normalized);
        if ($length < 2 || $length > 255) {
            throw new \InvalidArgumentException('Product name length must be between 2 and 255 characters.');
        }

        return new self($normalized);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
