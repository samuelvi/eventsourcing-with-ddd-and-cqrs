<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\UserReadRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;

final readonly class DbalUserReadRepository implements UserReadRepositoryInterface
{
    public function __construct(
        private ReadEntityManager $entityManager,
    ) {}

    public function findAllForList(): array
    {
        $sql = 'SELECT id, name, email FROM users ORDER BY id DESC';
        return $this->entityManager->query($sql);
    }

    public function findById(string $id): ?array
    {
        $sql = 'SELECT id, name, email FROM users WHERE id = :id';
        return $this->entityManager->fetchOne($sql, ['id' => $id]);
    }

    public function countAll(): int
    {
        $sql = 'SELECT COUNT(*) FROM users';
        return (int) $this->entityManager->fetchOne($sql)['count'];
    }

    public function existsByEmail(string $email): bool
    {
        $sql = 'SELECT 1 FROM users WHERE email = :email LIMIT 1';
        return (bool) $this->entityManager->fetchOne($sql, ['email' => $email]);
    }
}
