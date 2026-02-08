<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\EntityManagerInterface;

final readonly class WriteEntityManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function persist(object $entity): void
    {
        $this->entityManager->persist($entity);
    }

    public function remove(object $entity): void
    {
        $this->entityManager->remove($entity);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    /**
     * Use sparingly, only when strict consistency is needed for business logic checks.
     */
    public function find(string $className, mixed $id): ?object
    {
        return $this->entityManager->find($className, $id);
    }

    public function getRepository(string $className): \Doctrine\Persistence\ObjectRepository
    {
        return $this->entityManager->getRepository($className);
    }
}
