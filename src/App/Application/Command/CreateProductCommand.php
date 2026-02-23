<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\ValueObject\Currency;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\ProductName;
use App\Domain\ValueObject\ProductType;
use App\Domain\ValueObject\UuidString;

final readonly class CreateProductCommand
{
    private function __construct(
        public string $name,
        public float $price,
        public string $currency,
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
        string $currency,
        string $supplierId,
        string $type,
        array $detailsData
    ): self {
        return new self($name, $price, $currency, $supplierId, $type, $detailsData);
    }

    public function nameVO(): ProductName
    {
        return ProductName::fromString($this->name);
    }

    public function priceVO(): Money
    {
        return Money::fromFloat($this->price, Currency::fromString($this->currency));
    }

    public function typeVO(): ProductType
    {
        return ProductType::fromString($this->type);
    }

    public function supplierIdVO(): UuidString
    {
        return UuidString::fromString($this->supplierId);
    }
}
