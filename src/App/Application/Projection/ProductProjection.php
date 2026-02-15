<?php

declare(strict_types=1);

namespace App\Application\Projection;

use App\Domain\Event\ProductRegistered;
use App\Domain\Model\ProductEntity;
use App\Domain\Repository\ProductWriteRepositoryInterface;
use App\Domain\Repository\SupplierWriteRepositoryInterface;
use App\Infrastructure\EventSourcing\ProjectionCheckpoint;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

final readonly class ProductProjection
{
    public function __construct(
        private ProductWriteRepositoryInterface $productWriteRepository,
        private SupplierWriteRepositoryInterface $supplierRepository,
        private MongoStore $mongoStore,
    ) {}

    #[AsMessageHandler]
    public function __invoke(ProductRegistered $event): void
    {
        $supplier = $this->supplierRepository->getById($event->supplierId);

        $product = ProductEntity::hydrate(
            id: Uuid::fromString($event->productId),
            name: $event->name,
            price: $event->price,
            currency: $event->currency,
            type: $event->type,
            supplier: $supplier,
            externalReferenceId: Uuid::fromString($event->productId) // Usamos el mismo ID para simplificar el vínculo
        );

        $this->productWriteRepository->save($product);

        // Update Checkpoint
        $this->updateCheckpoint($event->productId);
    }

    private function updateCheckpoint(string $lastEventId): void
    {
        $checkpoint = $this->mongoStore->findCheckpoint('product_projection');
        if (!$checkpoint) {
            $checkpoint = ProjectionCheckpoint::create('product_projection');
        }
        $checkpoint->update(Uuid::fromString($lastEventId));
        $this->mongoStore->saveCheckpoint($checkpoint);
    }
}
