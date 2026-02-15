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
     * @return array<array{id: string, price: float, supplier_id: string}>
     */
    public function findByBudget(float $budget): array
    {
        // Find products (type=menu) where price is within 10% range of budget or less
        $sql = "SELECT id, price, supplier_id FROM products WHERE price <= :budget AND type = 'menu' ORDER BY price DESC";
        /** @var array<array{id: string, price: float, supplier_id: string}> */
        return $this->entityManager->query($sql, ['budget' => $budget]);
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function findAllForList(): array
    {
        $sql = 'SELECT id, name, price, currency, type, external_reference_id, supplier_id FROM products ORDER BY name ASC';
        return $this->entityManager->query($sql);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        $sql = 'SELECT id, name, price, currency, type, external_reference_id, supplier_id FROM products WHERE id = :id';
        return $this->entityManager->fetchOne($sql, ['id' => $id]);
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function findCatalog(): array
    {
        $sql = '
            SELECT 
                p.id, 
                p.name, 
                p.price, 
                p.currency, 
                p.type, 
                p.external_reference_id, 
                p.supplier_id,
                m.title as menu_title,
                m.description as menu_description
            FROM products p
            LEFT JOIN menus m ON p.external_reference_id = m.id AND p.type = \'menu\'
            ORDER BY p.name ASC
        ';
        return $this->entityManager->query($sql);
    }

    public function countAll(): int
    {
        $sql = 'SELECT COUNT(*) FROM products';
        $result = $this->entityManager->fetchOne($sql);
        
        return isset($result['count']) ? TypeAssert::int($result['count']) : 0;
    }
}
