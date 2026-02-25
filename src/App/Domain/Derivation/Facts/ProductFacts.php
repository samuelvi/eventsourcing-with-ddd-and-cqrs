<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Facts;

final readonly class ProductFacts
{
    public function __construct(
        public string $productId,
        public float $price,
    ) {}
}
