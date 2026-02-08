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

    public function getRepository(string $className): \Doctrine\Persistence\ObjectRepository
    {
        return $this->entityManager->getRepository($className);
    }

    public function save(object $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->entityManager->getConnection()->executeStatement($sql, $params);
    }
}
