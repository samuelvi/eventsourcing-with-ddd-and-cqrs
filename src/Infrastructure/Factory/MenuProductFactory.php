<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Domain\Model\MenuEntity;
use App\Domain\Model\ProductEntity;
use App\Domain\Model\SupplierEntity;
use App\Domain\Repository\MenuWriteRepositoryInterface;
use App\Domain\Repository\MenuReadRepositoryInterface;
use App\Domain\Service\ProductDetailFactoryInterface;
use App\Domain\Shared\TypeAssert;
use Symfony\Component\Uid\Uuid;

final readonly class MenuProductFactory implements ProductDetailFactoryInterface
{
    public function __construct(
        private MenuWriteRepositoryInterface $menuWriteRepository,
        private MenuReadRepositoryInterface $menuReadRepository,
    ) {}

    public function supports(string $type): bool
    {
        return $type === ProductEntity::TYPE_MENU;
    }

    public function create(array $data, SupplierEntity $supplier): Uuid
    {
        $menu = MenuEntity::create(
            title: isset($data['title']) ? TypeAssert::string($data['title']) : 'Untitled Menu',
            price: isset($data['price']) ? TypeAssert::float($data['price']) : 0.0,
            currency: isset($data['currency']) ? TypeAssert::string($data['currency']) : 'EUR',
            supplier: $supplier,
            description: isset($data['description']) ? TypeAssert::string($data['description']) : null
        );

        $this->menuWriteRepository->save($menu);

        return $menu->id;
    }

    public function getDetails(Uuid $referenceId): ?object
    {
        $data = $this->menuReadRepository->findById($referenceId->toRfc4122());

        if (!$data) {
            return null;
        }

        return (object) $data; 
    }
}