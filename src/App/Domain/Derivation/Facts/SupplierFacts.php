<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Facts;

final readonly class SupplierFacts
{
    public function __construct(
        public string $supplierId,
        public ?string $country,
        public bool $isActive,
    ) {}
}
