<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Model\ProductEntity;
use App\Domain\Repository\ProductWriteRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\WriteEntityManager;

final readonly class DoctrineProductWriteRepository implements ProductWriteRepositoryInterface
{
    public function __construct(
        private WriteEntityManager $entityManager,
    ) {}

    public function save(ProductEntity $product): void
    {
        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }
}
