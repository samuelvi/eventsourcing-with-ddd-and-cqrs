<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\SupplierEntity;

interface SupplierWriteRepositoryInterface
{
    /**
     * @throws \Exception If not found
     */
    public function getById(string $id): SupplierEntity;

    public function save(SupplierEntity $supplier): void;
}
