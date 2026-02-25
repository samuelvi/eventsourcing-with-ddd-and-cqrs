<?php

declare(strict_types=1);

namespace App\Domain\Derivation;

final readonly class DerivationContext
{
    public function __construct(
        public ?string $bookingCountry,
        public ?string $supplierCountry,
    ) {}
}
