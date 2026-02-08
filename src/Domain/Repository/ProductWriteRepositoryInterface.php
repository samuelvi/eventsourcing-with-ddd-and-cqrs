<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\ProductEntity;

interface ProductWriteRepositoryInterface
{
    public function save(ProductEntity $product): void;
}
