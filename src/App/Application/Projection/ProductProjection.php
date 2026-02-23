<?php

declare(strict_types=1);

namespace App\Application\Projection;

use App\Domain\Event\ProductRegistered;
use App\Domain\Model\ProductEntity;
use App\Domain\Repository\ProductWriteRepositoryInterface;
use App\Domain\Repository\SupplierWriteRepositoryInterface;
use App\Domain\ValueObject\Currency;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\ProductName;
use App\Domain\ValueObject\ProductType;
use App\Domain\ValueObject\UuidString;
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
        $supplierId = UuidString::fromString($event->supplierId);
        $productId = UuidString::fromString($event->productId);
        $name = ProductName::fromString($event->name);
        $type = ProductType::fromString($event->type);
        $price = Money::fromFloat($event->price, Currency::fromString($event->currency));

        $supplier = $this->supplierRepository->getById($supplierId->toString());

        $product = ProductEntity::hydrate(
            id: Uuid::fromString($productId->toString()),
            name: $name->toString(),
            price: $price->amount()->toFloat(),
            currency: $price->currency()->toString(),
            type: $type->toString(),
            supplier: $supplier,
            externalReferenceId: Uuid::fromString($productId->toString()) // Usamos el mismo ID para simplificar el vínculo
        );

        $this->productWriteRepository->save($product);

        // Update Checkpoint
        $this->updateCheckpoint($productId->toString());
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
