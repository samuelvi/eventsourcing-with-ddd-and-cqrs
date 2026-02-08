<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Model\UserEntity;
use App\Domain\Repository\UserWriteRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\WriteEntityManager;

final readonly class DoctrineUserWriteRepository implements UserWriteRepositoryInterface
{
    public function __construct(
        private WriteEntityManager $entityManager,
    ) {}

    public function save(UserEntity $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function remove(UserEntity $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
