<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Candidate;

use App\Domain\Derivation\DerivationContext;
use App\Domain\Derivation\Facts\BookingFacts;
use App\Domain\Derivation\Facts\ProductFacts;
use App\Domain\Derivation\Facts\SupplierFacts;
use App\Domain\Shared\TypeAssert;

final readonly class QuoteCandidate
{
    private function __construct(
        public string $supplierId,
        public string $productId,
        public float $price,
        public ?string $supplierCountry,
        public bool $supplierIsActive,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            supplierId: TypeAssert::string($row['supplier_id']),
            productId: TypeAssert::string($row['id']),
            price: TypeAssert::float($row['price']),
            supplierCountry: isset($row['supplier_country']) ? TypeAssert::string($row['supplier_country']) : null,
            supplierIsActive: (bool) ($row['supplier_is_active'] ?? false),
        );
    }

    public function toDerivationContext(BookingFacts $bookingFacts): DerivationContext
    {
        return new DerivationContext(
            booking: $bookingFacts,
            supplier: new SupplierFacts(
                supplierId: $this->supplierId,
                country: $this->supplierCountry,
                isActive: $this->supplierIsActive,
            ),
            product: new ProductFacts(
                productId: $this->productId,
                price: $this->price,
            ),
        );
    }
}
