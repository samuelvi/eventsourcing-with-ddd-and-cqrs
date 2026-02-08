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
        $sql = 'SELECT * FROM users WHERE id = :id';
        return $this->entityManager->fetchOne($sql, ['id' => $id]);
    }
}
