<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Product;

use App\Domain\Model\Product;
use App\Domain\Repository\ProductEventStoreRepositoryInterface;
use App\Infrastructure\Repository\Shared\EventSourcingRepository;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @extends EventSourcingRepository<Product>
 */
final readonly class ProductEventSourcingRepository extends EventSourcingRepository implements ProductEventStoreRepositoryInterface
{
    public function __construct(
        MongoStore $mongoStore,
        SerializerInterface $serializer,
        int $snapshotThreshold,
    ) {
        parent::__construct(Product::class, $mongoStore, $serializer, $snapshotThreshold);
    }
}
