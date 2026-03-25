<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface ProductReadRepositoryInterface
{
    /**
     * @return array<array<string, mixed>>
     */
    public function findAllForList(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array;

    /**
     * @return array<array{id: string, price: float, supplier_id: string}>
     */
    public function findByBudget(float $budget): array;

    /**
     * @return array<array{id: string, price: float, supplier_id: string, supplier_country: string|null, supplier_is_active: bool}>
     */
    public function findCandidatesFirstFilter(float $budget, string $bookingId, ?string $country = null): array;

    /**
     * @param array<int, string> $supplierIds
     * @return array<array<string, mixed>>
     */
    public function findBySupplierIds(array $supplierIds): array;

    /**
     * @return array<array<string, mixed>>
     */
    public function findCatalog(): array;

    public function countAll(): int;
}
