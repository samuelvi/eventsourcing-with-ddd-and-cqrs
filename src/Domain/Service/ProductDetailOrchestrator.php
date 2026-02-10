<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Model\SupplierEntity;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Uid\Uuid;

final readonly class ProductDetailOrchestrator
{
    /**
     * @param iterable<ProductDetailFactoryInterface> $factories
     */
    public function __construct(
        #[AutowireIterator('app.product_detail_factory')]
        private iterable $factories,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function createDetails(string $type, array $data, SupplierEntity $supplier): Uuid
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($type)) {
                return $factory->create($data, $supplier);
            }
        }

        throw new \InvalidArgumentException(sprintf('Unsupported product type: %s', $type));
    }

    public function getDetails(string $type, Uuid $referenceId): ?object
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($type)) {
                return $factory->getDetails($referenceId);
            }
        }

        return null;
    }
}
