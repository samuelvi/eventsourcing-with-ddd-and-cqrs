<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class ProductType
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            throw new \InvalidArgumentException('Product type cannot be empty.');
        }

        if (!preg_match('/^[a-z0-9_\\-]{2,50}$/', $normalized)) {
            throw new \InvalidArgumentException('Product type format is invalid.');
        }

        return new self($normalized);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
