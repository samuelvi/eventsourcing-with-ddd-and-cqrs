<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\CreateProductCommand;
use App\Domain\Model\ProductEntity;
use App\Domain\Model\SupplierEntity;
use App\Domain\Repository\ProductWriteRepositoryInterface;
use App\Domain\Service\ProductDetailOrchestrator;
use App\Infrastructure\Persistence\Doctrine\WriteEntityManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateProductHandler
{
    public function __construct(
        private WriteEntityManager $entityManager, // To fetch Supplier
        private ProductDetailOrchestrator $orchestrator,
        private ProductWriteRepositoryInterface $productRepository,
    ) {}

    public function __invoke(CreateProductCommand $command): void
    {
        /** @var SupplierEntity|null $supplier */
        $supplier = $this->entityManager->find(SupplierEntity::class, $command->supplierId);

        if (!$supplier) {
            throw new \InvalidArgumentException('Supplier not found');
        }

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
