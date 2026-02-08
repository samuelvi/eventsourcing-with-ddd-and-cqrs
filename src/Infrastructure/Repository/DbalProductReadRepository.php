<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\ProductReadRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;

final readonly class DbalProductReadRepository implements ProductReadRepositoryInterface
{
    public function __construct(
        private ReadEntityManager $entityManager,
    ) {}

    public function findAllForList(): array
    {
        $sql = 'SELECT * FROM products ORDER BY name ASC';
        return $this->entityManager->query($sql);
    }

    public function findById(int $id): ?array
    {
        $sql = 'SELECT * FROM products WHERE id = :id';
        return $this->entityManager->fetchOne($sql, ['id' => $id]);
    }
}
