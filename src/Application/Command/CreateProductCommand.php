<?php

declare(strict_types=1);

namespace App\Application\Command;

final readonly class CreateProductCommand
{
    public function __construct(
        public string $name,
        public float $price,
        public string $supplierId,
        public string $type,
        /** @var array<string, mixed> Data specific to the product type (e.g., Menu fields) */
        public array $detailsData,
    ) {}
}
