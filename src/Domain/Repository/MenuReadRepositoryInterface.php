<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface MenuReadRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array;

    /**
     * @return array<array{id: string, price: float, supplier_id: string}>
     */
    public function findByBudget(float $budget): array;
}
