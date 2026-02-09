<?php

declare(strict_types=1);

namespace App\Application\Command;

final readonly class CreateProductCommand
{
    private function __construct(
        public string $name,
        public float $price,
        public string $supplierId,
        public string $type,
        /** @var array<string, mixed> Data specific to the product type (e.g., Menu fields) */
        public array $detailsData,
    ) {}

    /**
     * @param array<string, mixed> $detailsData
     */
    public static function create(
        string $name,
        float $price,
        string $supplierId,
        string $type,
        array $detailsData
    ): self {
        return new self($name, $price, $supplierId, $type, $detailsData);
    }
}
