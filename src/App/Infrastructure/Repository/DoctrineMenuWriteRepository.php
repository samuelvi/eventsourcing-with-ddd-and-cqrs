<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Model\MenuEntity;
use App\Domain\Repository\MenuWriteRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\WriteEntityManager;

final readonly class DoctrineMenuWriteRepository implements MenuWriteRepositoryInterface
{
    public function __construct(
        private WriteEntityManager $entityManager,
    ) {}

    public function save(MenuEntity $menu): void
    {
        $this->entityManager->persist($menu);
        $this->entityManager->flush();
    }
}
