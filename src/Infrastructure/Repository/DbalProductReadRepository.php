<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\ProductReadRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use App\Domain\Shared\TypeAssert;

final readonly class DbalProductReadRepository implements ProductReadRepositoryInterface
{
    public function __construct(
        private ReadEntityManager $entityManager,
    ) {}

    /**
     * @return array<array<string, mixed>>
     */
    public function findAllForList(): array
    {
        $sql = 'SELECT id, name, price, type, external_reference_id, supplier_id FROM products ORDER BY name ASC';
        return $this->entityManager->query($sql);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        $sql = 'SELECT id, name, price, type, external_reference_id, supplier_id FROM products WHERE id = :id';
        return $this->entityManager->fetchOne($sql, ['id' => $id]);
    }

    public function countAll(): int
    {
        $sql = 'SELECT COUNT(*) FROM products';
        $result = $this->entityManager->fetchOne($sql);
        
        return isset($result['count']) ? TypeAssert::int($result['count']) : 0;
    }
}
