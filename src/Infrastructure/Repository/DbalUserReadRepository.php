<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\UserReadRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use App\Domain\Shared\TypeAssert;

final readonly class DbalUserReadRepository implements UserReadRepositoryInterface
{
    public function __construct(
        private ReadEntityManager $entityManager,
    ) {}

    /**
     * @return array<array{id: string, name: string, email: string}>
     */
    public function findAllForList(): array
    {
        $sql = 'SELECT id, name, email FROM users ORDER BY id DESC';
        /** @var array<array{id: string, name: string, email: string}> */
        return $this->entityManager->query($sql);
    }

    /**
     * @return array{id: string, name: string, email: string}|null
     */
    public function findById(string $id): ?array
    {
        $sql = 'SELECT id, name, email FROM users WHERE id = :id';
        $result = $this->entityManager->fetchOne($sql, ['id' => $id]);
        /** @var array{id: string, name: string, email: string}|null $result */
        return $result;
    }

    public function countAll(): int
    {
        $sql = 'SELECT COUNT(*) FROM users';
        $result = $this->entityManager->fetchOne($sql);

        return isset($result['count']) ? TypeAssert::int($result['count']) : 0;
    }

    public function existsByEmail(string $email): bool
    {
        $sql = 'SELECT 1 FROM users WHERE email = :email LIMIT 1';
        return (bool) $this->entityManager->fetchOne($sql, ['email' => $email]);
    }

    public function exists(string $id): bool
    {
        $sql = 'SELECT 1 FROM users WHERE id = :id LIMIT 1';
        return (bool) $this->entityManager->fetchOne($sql, ['id' => $id]);
    }
}
