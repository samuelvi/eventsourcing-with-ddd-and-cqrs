<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\CreateProductCommand;
use App\Domain\Model\ProductEntity;
use App\Domain\Model\SupplierEntity;
use App\Domain\Repository\ProductWriteRepositoryInterface;
use App\Domain\Repository\SupplierWriteRepositoryInterface;
use App\Domain\Service\ProductDetailOrchestrator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateProductHandler
{
    public function __construct(
        private SupplierWriteRepositoryInterface $supplierRepository,
        private ProductDetailOrchestrator $orchestrator,
        private ProductWriteRepositoryInterface $productRepository,
    ) {}

    public function __invoke(CreateProductCommand $command): void
    {
        $supplier = $this->supplierRepository->getById($command->supplierId);

        // 1. Create the specific details (Menu, etc.) and get the ID
        $referenceId = $this->orchestrator->createDetails(
            $command->type,
            $command->detailsData,
            $supplier
        );

        // 2. Create the generic Product linked to that ID
        $product = ProductEntity::create(
            name: $command->name,
            price: $command->price,
            supplier: $supplier,
            type: $command->type,
            externalReferenceId: $referenceId
        );

        $this->productRepository->save($product);
    }
}
