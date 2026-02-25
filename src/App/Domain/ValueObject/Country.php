<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class Country
{
    private function __construct(
        private string $value
    ) {
        if (!preg_match('/^[A-Z]{2}$/', $value)) {
            throw new \InvalidArgumentException('Country must be a valid ISO 3166-1 alpha-2 code.');
        }
    }

    public static function fromString(string $value): self
    {
        return new self(strtoupper(trim($value)));
    }

    public function toString(): string
    {
        return $this->value;
    }
}
