<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class UuidString
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $uuid = Uuid::fromString(trim($value));

        return new self($uuid->toRfc4122());
    }

    public function toString(): string
    {
        return $this->value;
    }
}
