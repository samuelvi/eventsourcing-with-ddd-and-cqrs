<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Model\UserEntity;
use App\Domain\Repository\UserReadRepositoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<UserEntity>
 */
final readonly class UserProvider implements ProviderInterface
{
    public function __construct(
        private UserReadRepositoryInterface $repository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (isset($uriVariables['id'])) {
            $data = $this->repository->findById($uriVariables['id']);
            if (!$data) return null;
            
            return UserEntity::hydrate(
                $data['name'], 
                $data['email'], 
                Uuid::fromString($data['id'])
            );
        }

        $data = $this->repository->findAllForList();

        return array_map(function (array $row) {
            return UserEntity::hydrate(
                $row['name'],
                $row['email'],
                Uuid::fromString($row['id'])
            );
        }, $data);
    }
}