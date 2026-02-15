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

    public function countAll(): int;
}
