<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Product;

/**
 * @extends EventStoreRepositoryInterface<Product>
 */
interface ProductEventStoreRepositoryInterface extends EventStoreRepositoryInterface
{
}
