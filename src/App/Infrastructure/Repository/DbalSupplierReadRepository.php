<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\SupplierReadRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;

final readonly class DbalSupplierReadRepository implements SupplierReadRepositoryInterface
{
    public function __construct(
        private ReadEntityManager $entityManager,
    ) {}

    /**
     * @return array<array<string, mixed>>
     */
    public function findOptimalSuppliers(int $limit = 3): array
    {
        $sql = 'SELECT id, name, is_active, rating FROM suppliers WHERE is_active = true ORDER BY rating DESC LIMIT :limit';
        return $this->entityManager->query($sql, ['limit' => $limit]);
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function findAllForList(): array
    {
        $sql = 'SELECT id, name, is_active, rating FROM suppliers ORDER BY name ASC';
        return $this->entityManager->query($sql);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        $sql = 'SELECT id, name, is_active, rating FROM suppliers WHERE id = :id';
        $result = $this->entityManager->fetchOne($sql, ['id' => $id]);

        return $result ?: null;
    }
}
