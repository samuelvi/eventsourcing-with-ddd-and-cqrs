<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Quote;

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

    public function callbackUpdate(string $quoteId, string $callbackUrl): int
    {
        return (int) $this->entityManager->getConnection()->executeStatement(
            'UPDATE quotes SET n8n_callback_url = :callbackUrl WHERE id = :quoteId',
            [
                'quoteId' => $quoteId,
                'callbackUrl' => $callbackUrl,
            ]
        );
    }
}
