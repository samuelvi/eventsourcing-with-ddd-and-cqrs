<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Model\QuoteEntity;
use App\Domain\Repository\QuoteWriteRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineQuoteWriteRepository implements QuoteWriteRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function save(QuoteEntity $quote): void
    {
        $this->entityManager->persist($quote);
        $this->entityManager->flush();
    }
}
