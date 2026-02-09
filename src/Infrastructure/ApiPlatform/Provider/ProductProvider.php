<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Model\ProductEntity;
use App\Domain\Model\SupplierEntity;
use App\Domain\Repository\ProductReadRepositoryInterface;
use App\Domain\Service\ProductDetailOrchestrator;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<ProductEntity>
 */
final readonly class ProductProvider implements ProviderInterface
{
    public function __construct(
        private ProductReadRepositoryInterface $repository,
        private ProductDetailOrchestrator $orchestrator,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (isset($uriVariables['id'])) {
            $data = $this->repository->findById($uriVariables['id']);
            
            if (!$data) {
                return null;
            }

            // Hydrate logic duplicated - ideally extract to private method
            $supplier = SupplierEntity::hydrate(
                Uuid::fromString($data['supplier_id']),
                'Unknown', 
                true,
                0.0
            );

            $product = ProductEntity::hydrate(
                Uuid::fromString($data['id']),
                $data['name'],
                (float) $data['price'],
                $data['type'],
                $supplier,
                $data['external_reference_id'] ? Uuid::fromString($data['external_reference_id']) : null
            );

            if ($data['external_reference_id']) {
                $product->setDetails($this->orchestrator->getDetails(
                    $data['type'], 
                    Uuid::fromString($data['external_reference_id'])
                ));
            }

            return $product;
        }

        $productsData = $this->repository->findAllForList();
        
        // Map scalar data to virtual objects with details
        return array_map(function(array $data) {
            // Partial hydration of supplier (only ID is strictly needed for link, but name helps if available)
            // Note: DBAL query for findAllForList might need to fetch supplier_id
            $supplier = SupplierEntity::hydrate(
                Uuid::fromString($data['supplier_id']),
                'Unknown', // We don't join supplier table in list for performance, or we could.
                true,
                0.0
            );

            $product = ProductEntity::hydrate(
                Uuid::fromString($data['id']),
                $data['name'],
                (float) $data['price'],
                $data['type'],
                $supplier,
                $data['external_reference_id'] ? Uuid::fromString($data['external_reference_id']) : null
            );

            if ($data['external_reference_id']) {
                $product->setDetails($this->orchestrator->getDetails(
                    $data['type'], 
                    Uuid::fromString($data['external_reference_id'])
                ));
            }

            return $product;
        }, $productsData);
    }
}
