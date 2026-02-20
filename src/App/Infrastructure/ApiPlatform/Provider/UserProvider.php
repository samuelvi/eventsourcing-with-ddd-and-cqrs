<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Model\UserEntity;
use App\Domain\Repository\UserReadRepositoryInterface;
use App\Domain\Shared\TypeAssert;
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
            $data = $this->repository->findById($this->resolveUserId($uriVariables['id']));
            if (!$data) return null;
            
            return UserEntity::hydrate(
                TypeAssert::string($data['name']), 
                TypeAssert::string($data['email']), 
                Uuid::fromString(TypeAssert::string($data['id'])),
                isset($data['created_at']) ? new \DateTimeImmutable(TypeAssert::string($data['created_at'])) : null
            );
        }

        $data = $this->repository->findAllForList();

        return array_map(function (array $row) {
            return UserEntity::hydrate(
                TypeAssert::string($row['name']),
                TypeAssert::string($row['email']),
                Uuid::fromString(TypeAssert::string($row['id'])),
                isset($row['created_at']) ? new \DateTimeImmutable(TypeAssert::string($row['created_at'])) : null
            );
        }, $data);
    }

    private function resolveUserId(mixed $id): string
    {
        if (is_string($id)) {
            return $id;
        }

        if ($id instanceof Uuid) {
            return $id->toRfc4122();
        }

        throw new \InvalidArgumentException('User id must be a string or UUID.');
    }
}
