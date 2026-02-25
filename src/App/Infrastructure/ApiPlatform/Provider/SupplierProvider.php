<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use App\Domain\Model\ProductEntity;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Model\SupplierEntity;
use App\Domain\Repository\ProductReadRepositoryInterface;
use App\Domain\Repository\SupplierReadRepositoryInterface;
use App\Domain\Shared\TypeAssert;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<SupplierEntity>
 */
final readonly class SupplierProvider implements ProviderInterface
{
    public function __construct(
        private SupplierReadRepositoryInterface $repository,
        private ProductReadRepositoryInterface $productReadRepository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (isset($uriVariables['id'])) {
            $row = $this->repository->findById(TypeAssert::string($uriVariables['id']));

            if (!$row) {
                return null;
            }

            $supplier = $this->hydrateSupplier($row);
            $this->attachProducts([$supplier]);

            return $supplier;
        }

        $data = $this->repository->findAllForList();
        $suppliers = array_map(fn (array $row): SupplierEntity => $this->hydrateSupplier($row), $data);
        $this->attachProducts($suppliers);

        return $suppliers;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateSupplier(array $row): SupplierEntity
    {
        return SupplierEntity::hydrate(
            Uuid::fromString(TypeAssert::string($row['id'])),
            TypeAssert::string($row['name']),
            (bool) $row['is_active'],
            TypeAssert::float($row['rating']),
            isset($row['country']) ? TypeAssert::string($row['country']) : null
        );
    }

    /**
     * @param array<int, SupplierEntity> $suppliers
     */
    private function attachProducts(array $suppliers): void
    {
        if ($suppliers === []) {
            return;
        }

        $supplierIndex = [];
        foreach ($suppliers as $supplier) {
            $supplierIndex[$supplier->id->toRfc4122()] = $supplier;
        }

        $productsData = $this->productReadRepository->findBySupplierIds(array_keys($supplierIndex));

        foreach ($productsData as $data) {
            $supplierId = TypeAssert::string($data['supplier_id']);
            $supplier = $supplierIndex[$supplierId] ?? null;
            if (!$supplier instanceof SupplierEntity) {
                continue;
            }

            $externalReferenceId = isset($data['external_reference_id']) && $data['external_reference_id'] !== null
                ? Uuid::fromString(TypeAssert::string($data['external_reference_id']))
                : null;

            $product = ProductEntity::hydrate(
                id: Uuid::fromString(TypeAssert::string($data['id'])),
                name: TypeAssert::string($data['name']),
                price: TypeAssert::float($data['price']),
                currency: TypeAssert::string($data['currency']),
                type: TypeAssert::string($data['type']),
                supplier: $supplier,
                externalReferenceId: $externalReferenceId,
            );

            $supplier->products->add($product);
        }
    }
}
