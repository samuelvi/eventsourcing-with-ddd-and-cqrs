<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Model\ProductEntity;
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
            return null;
        }

        $productsData = $this->repository->findAllForList();
        
        // Map scalar data to virtual objects with details
        return array_map(function(array $data) {
            $product = [
                'id' => $data['id'],
                'name' => $data['name'],
                'price' => (float) $data['price'],
                'type' => $data['type'],
                'externalReferenceId' => $data['external_reference_id'],
                'details' => null
            ];

            if ($data['external_reference_id']) {
                $product['details'] = $this->orchestrator->getDetails(
                    $data['type'], 
                    Uuid::fromString($data['external_reference_id'])
                );
            }

            return $product;
        }, $productsData);
    }
}
