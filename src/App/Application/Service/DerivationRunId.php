<?php

declare(strict_types=1);

namespace App\Application\Service;

use Symfony\Component\Uid\Uuid;

final readonly class DerivationRunId
{
    private function __construct(
        private string $value,
    ) {}

    public static function generate(): self
    {
        return new self(Uuid::v7()->toRfc4122());
    }

    public static function fromString(string $value): self
    {
        return new self(Uuid::fromString(trim($value))->toRfc4122());
    }

    public function toString(): string
    {
        return $this->value;
    }
}
