<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Model\SupplierEntity;
use App\Domain\Repository\SupplierWriteRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\WriteEntityManager;
use Doctrine\ORM\EntityNotFoundException;

final readonly class DoctrineSupplierWriteRepository implements SupplierWriteRepositoryInterface
{
    public function __construct(
        private WriteEntityManager $entityManager,
    ) {}

    public function getById(string $id): SupplierEntity
    {
        /** @var \Doctrine\ORM\EntityRepository<SupplierEntity> $repo */
        $repo = $this->entityManager->getRepository(SupplierEntity::class);
        $qb = $repo->createQueryBuilder('s');
        
        $supplier = $qb
            ->where('s.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$supplier instanceof SupplierEntity) {
            throw new EntityNotFoundException(sprintf('Supplier with ID "%s" not found.', $id));
        }

        return $supplier;
    }

    public function save(SupplierEntity $supplier): void
    {
        $this->entityManager->persist($supplier);
        $this->entityManager->flush();
    }
}
