<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class Address
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $normalized = trim($value);
        if ($normalized === '') {
            throw new \InvalidArgumentException('Address cannot be empty.');
        }

        if (mb_strlen($normalized) > 255) {
            throw new \InvalidArgumentException('Address cannot exceed 255 characters.');
        }

        return new self($normalized);
    }

    public static function fromNullable(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        return self::fromString($normalized);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
