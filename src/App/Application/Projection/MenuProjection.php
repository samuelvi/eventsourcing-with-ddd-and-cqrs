<?php

declare(strict_types=1);

namespace App\Application\Projection;

use App\Domain\Event\ProductRegistered;
use App\Domain\Model\MenuEntity;
use App\Domain\Model\ProductEntity;
use App\Domain\Repository\MenuWriteRepositoryInterface;
use App\Domain\Repository\SupplierWriteRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

final readonly class MenuProjection
{
    public function __construct(
        private MenuWriteRepositoryInterface $menuWriteRepository,
        private SupplierWriteRepositoryInterface $supplierRepository,
    ) {}

    #[AsMessageHandler]
    public function __invoke(ProductRegistered $event): void
    {
        if ($event->type !== ProductEntity::TYPE_MENU) {
            return;
        }

        $supplier = $this->supplierRepository->getById($event->supplierId);

        // Los datos específicos del menú vienen en $event->details
        $menu = MenuEntity::hydrate(
            id: Uuid::fromString($event->productId),
            title: $event->details['title'] ?? $event->name,
            description: $event->details['description'] ?? null
        );

        $this->menuWriteRepository->save($menu);
    }
}
