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

    public function findById(string $id): ?array
    {
        $sql = 'SELECT id, title, description, price, currency, supplier_id FROM menus WHERE id = :id';
        return $this->entityManager->fetchOne($sql, ['id' => $id]);
    }
}
