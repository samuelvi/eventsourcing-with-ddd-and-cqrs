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

        private MenuReadRepositoryInterface $menuReadRepository,

    ) {}



    public function supports(string $type): bool

    {

        return $type === ProductEntity::TYPE_MENU;

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
