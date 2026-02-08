<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\SupplierEntity;

interface SupplierReadRepositoryInterface
{
    /**
     * @return array<SupplierEntity>
     */
    public function findOptimalSuppliers(int $limit = 3): array;
}
