<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class Email
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            throw new \InvalidArgumentException('Email cannot be empty.');
        }

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Email format is invalid.');
        }

        return new self($normalized);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
