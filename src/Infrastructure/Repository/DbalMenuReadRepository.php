<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\MenuReadRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;

final readonly class DbalMenuReadRepository implements MenuReadRepositoryInterface
{
    public function __construct(
        private ReadEntityManager $entityManager,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        $sql = 'SELECT id, title, description, price, currency, supplier_id FROM menus WHERE id = :id';
        return $this->entityManager->fetchOne($sql, ['id' => $id]);
    }

    /**
     * @return array<array{id: string, price: float, supplier_id: string}>
     */
    public function findByBudget(float $budget): array
    {
        // Find menus where price is within 10% range of budget or less
        $sql = 'SELECT id, price, supplier_id FROM menus WHERE price <= :budget ORDER BY price DESC';
        /** @var array<array{id: string, price: float, supplier_id: string}> */
        return $this->entityManager->query($sql, ['budget' => $budget]);
    }
}
