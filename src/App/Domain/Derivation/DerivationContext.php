<?php

declare(strict_types=1);

namespace App\Domain\Derivation;

use App\Domain\Derivation\Facts\BookingFacts;
use App\Domain\Derivation\Facts\ProductFacts;
use App\Domain\Derivation\Facts\SupplierFacts;

final readonly class DerivationContext
{
    public function __construct(
        public BookingFacts $booking,
        public SupplierFacts $supplier,
        public ProductFacts $product,
    ) {}
}
