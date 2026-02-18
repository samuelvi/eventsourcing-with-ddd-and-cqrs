<?php

declare(strict_types=1);

namespace App\Application\Projection;

use App\Domain\Event\ProductRegistered;
use App\Domain\Model\MenuEntity;
use App\Domain\Model\ProductEntity;
use App\Domain\Repository\MenuWriteRepositoryInterface;
use App\Domain\Repository\SupplierWriteRepositoryInterface;
use App\Domain\Shared\TypeAssert;
use App\Infrastructure\EventSourcing\ProjectionCheckpoint;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

final readonly class MenuProjection
{
    public function __construct(
        private MenuWriteRepositoryInterface $menuWriteRepository,
        private SupplierWriteRepositoryInterface $supplierRepository,
        private MongoStore $mongoStore,
    ) {}

    #[AsMessageHandler]
    public function __invoke(ProductRegistered $event): void
    {
        if ($event->type !== ProductEntity::TYPE_MENU) {
            return;
        }

        $supplier = $this->supplierRepository->getById($event->supplierId);

        $title = isset($event->details['title']) ? TypeAssert::string($event->details['title']) : $event->name;
        $description = isset($event->details['description']) ? TypeAssert::string($event->details['description']) : null;

        $menu = MenuEntity::hydrate(
            id: Uuid::fromString($event->productId),
            title: $title,
            description: $description
        );

        $this->menuWriteRepository->save($menu);

        // Update Checkpoint
        $this->updateCheckpoint($event->productId);
    }

    private function updateCheckpoint(string $lastEventId): void
    {
        $checkpoint = $this->mongoStore->findCheckpoint('menu_projection');
        if (!$checkpoint) {
            $checkpoint = ProjectionCheckpoint::create('menu_projection');
        }
        $checkpoint->update(Uuid::fromString($lastEventId));
        $this->mongoStore->saveCheckpoint($checkpoint);
    }
}
