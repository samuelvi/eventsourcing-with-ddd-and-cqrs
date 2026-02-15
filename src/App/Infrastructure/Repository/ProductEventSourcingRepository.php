<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Model\Product;
use App\Domain\Repository\ProductEventStoreRepositoryInterface;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @extends EventSourcingRepository<Product>
 */
final readonly class ProductEventSourcingRepository extends EventSourcingRepository implements ProductEventStoreRepositoryInterface
{
    public function __construct(
        MongoStore $mongoStore,
        SerializerInterface $serializer,
        MessageBusInterface $eventBus,
    ) {
        parent::__construct(Product::class, $mongoStore, $serializer, $eventBus);
    }
}
