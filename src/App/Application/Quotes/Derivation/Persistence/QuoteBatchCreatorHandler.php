<?php

declare(strict_types=1);

namespace App\Application\Quotes\Derivation\Persistence;

use App\Application\Quotes\Derivation\Run\DerivationEventPublisherHandler;
use App\Application\Service\DerivationRunContext;
use App\Domain\Derivation\Candidate\QuoteCandidate;
use App\Domain\Derivation\Event\QuoteCreated;
use App\Domain\Model\QuoteEntity;
use App\Domain\Repository\QuoteWriteRepositoryInterface;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class QuoteBatchCreatorHandler
{
    public function __construct(
        private QuoteWriteRepositoryInterface $quoteWriteRepository,
        private DerivationEventPublisherHandler $eventPublisher,
    ) {}

    /**
     * @param array<int, QuoteCandidate> $candidates
     * @return array<int, string>
     */
    public function create(DerivationRunContext $context, array $candidates): array
    {
        $createdAt = new DateTimeImmutable();
        $seenPairs = [];
        $quoteIds = [];

        foreach ($candidates as $candidate) {
            $pairKey = $candidate->supplierId . ':' . $candidate->productId;
            if (isset($seenPairs[$pairKey])) {
                continue;
            }

            $seenPairs[$pairKey] = true;

            $quote = QuoteEntity::hydrate(
                id: Uuid::v7(),
                bookingId: Uuid::fromString($context->bookingId),
                supplierId: Uuid::fromString($candidate->supplierId),
                productId: Uuid::fromString($candidate->productId),
                price: $candidate->price,
                createdAt: $createdAt,
            );

            $this->quoteWriteRepository->save($quote);
            $quoteIds[] = $quote->id->toRfc4122();

            $this->eventPublisher->publishCreated(new QuoteCreated(
                quoteId: $quote->id->toRfc4122(),
                bookingId: $context->bookingId,
                supplierId: $candidate->supplierId,
                productId: $candidate->productId,
                price: $candidate->price,
                correlationId: $context->correlationId,
                occurredOn: $createdAt,
            ));
        }

        return $quoteIds;
    }
}
