<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Model\SupplierEntity;
use App\Domain\Repository\SupplierReadRepositoryInterface;

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
            return null;
        }

        return $this->repository->findAllForList();
    }
}
