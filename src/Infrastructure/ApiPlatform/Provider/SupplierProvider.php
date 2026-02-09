<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Model\SupplierEntity;
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
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (isset($uriVariables['id'])) {
            $row = $this->repository->findById(TypeAssert::string($uriVariables['id']));
            
            if (!$row) {
                return null;
            }

            return SupplierEntity::hydrate(
                Uuid::fromString(TypeAssert::string($row['id'])),
                TypeAssert::string($row['name']),
                (bool) $row['is_active'],
                TypeAssert::float($row['rating'])
            );
        }

        $data = $this->repository->findAllForList();

        return array_map(function (array $row) {
            return SupplierEntity::hydrate(
                Uuid::fromString(TypeAssert::string($row['id'])),
                TypeAssert::string($row['name']),
                (bool) $row['is_active'],
                TypeAssert::float($row['rating'])
            );
        }, $data);
    }
}
