<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\SupplierEntity;

interface SupplierReadRepositoryInterface
{
    /**
     * @return array<array<string, mixed>>
     */
    public function findOptimalSuppliers(int $limit = 3): array;

    /**
     * @return array<array<string, mixed>>
     */
    public function findAllForList(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array;
}
