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
        $sql = 'SELECT id, name, price, type, external_reference_id FROM products ORDER BY name ASC';
        return $this->entityManager->query($sql);
    }

    public function findById(int $id): ?array
    {
        $sql = 'SELECT id, name, price, type, external_reference_id FROM products WHERE id = :id';
        return $this->entityManager->fetchOne($sql, ['id' => $id]);
    }

    public function countAll(): int
    {
        $sql = 'SELECT COUNT(*) FROM products';
        return (int) $this->entityManager->fetchOne($sql)['count'];
    }
}
