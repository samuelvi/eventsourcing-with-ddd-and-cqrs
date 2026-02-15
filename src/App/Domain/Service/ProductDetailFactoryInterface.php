<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Model\ProductEntity;
use App\Domain\Model\SupplierEntity;
use Symfony\Component\Uid\Uuid;

interface ProductDetailFactoryInterface
{
    /**
     * Determines if this factory supports the given product type.
     */
    public function supports(string $type): bool;

    /**
     * Fetches the specific entity details using the reference ID.
     */
    public function getDetails(Uuid $referenceId): ?object;
}
