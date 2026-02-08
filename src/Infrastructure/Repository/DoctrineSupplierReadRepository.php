<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Model\SupplierEntity;
use App\Domain\Repository\SupplierReadRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * For complex object retrieval needed by Domain logic (not just scalar data),
 * sometimes using the ORM Read side is pragmatic if we need the Entities to attach to other Entities.
 * However, to be strict with the "ReadEntityManager" wrapping DBAL, we should hydrate manually or use IDs.
 *
 * For this example, we'll stick to ServiceEntityRepository for Supplier READS needed for logic,
 * but treat it as a Read-Side repository.
 *
 * @extends ServiceEntityRepository<SupplierEntity>
 */
final class DoctrineSupplierReadRepository extends ServiceEntityRepository implements SupplierReadRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupplierEntity::class);
    }

    public function findOptimalSuppliers(int $limit = 3): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.isActive = true')
            ->orderBy('s.rating', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
