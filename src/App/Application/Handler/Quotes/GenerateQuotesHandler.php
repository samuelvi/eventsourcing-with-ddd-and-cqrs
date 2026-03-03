<?php

declare(strict_types=1);

namespace App\Application\Handler\Quotes;

use App\Application\Command\Quotes\GenerateQuotesCommand;
use App\Domain\Derivation\Candidate\QuoteCandidate;
use App\Domain\Derivation\Event\QuoteLimited;
use App\Domain\Derivation\Event\QuoteLimitedByRules;
use App\Domain\Derivation\Facts\BookingFacts;
use App\Domain\Model\QuoteEntity;
use App\Domain\Repository\ProductReadRepositoryInterface;
use App\Domain\Repository\QuoteWriteRepositoryInterface;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class GenerateQuotesHandler
{
    public function __construct(
        private DerivationContextBuilder $contextBuilder,
        private ProductReadRepositoryInterface $productReadRepository,
        private QuoteCandidatesFilter $quoteCandidatesFilter,
        private QuoteWriteRepositoryInterface $quoteWriteRepository,
        private DerivationEventPublisher $eventPublisher,
    ) {}


    public function __invoke(GenerateQuotesCommand $command): void
    {
        $bookingId = $command->bookingIdVO()->toString();
        $bookingFacts = $this->contextBuilder->buildForBooking($bookingId);

        if ($bookingFacts === null) {
            return;
        }

        $candidates = $this->findCandidates($bookingFacts);
        if ($candidates === []) {
            return;
        }

        $this->eventPublisher->publishLimited(new QuoteLimited(
            correlationId: $command->correlationId,
            bookingId: $bookingId,
            limit: 4,
            totalCandidates: count($candidates),
            selected: false,
        ));



        $eligibleCandidates = $this->quoteCandidatesFilter->eligibleForBooking($bookingFacts, $candidates);

        $this->eventPublisher->publishLimitedByRules(new QuoteLimitedByRules(
            correlationId: $command->correlationId,
            bookingId: $bookingId,
            limit: 4,
            totalCandidates: count($eligibleCandidates),
            selected: false,
        ));

        if ($eligibleCandidates === []) {
            return;
        }

        //Almaceno las quotes
        $this->saveQuotes($bookingId, $eligibleCandidates);
    }

    /**
     * @return array<int, QuoteCandidate>
     */
    private function findCandidates(BookingFacts $bookingFacts): array
    {
        $candidatesData = $this->productReadRepository->findCandidatesFirstFilter(
            $bookingFacts->budget,
            $bookingFacts->country
        );

        return array_map(
            static fn (array $row): QuoteCandidate => QuoteCandidate::fromRow($row),
            $candidatesData
        );
    }



    /**
     * @param array<int, QuoteCandidate> $candidates
     */
    private function saveQuotes(string $bookingId, array $candidates): void
    {
        $createdAt = new DateTimeImmutable();
        $seenPairs = [];

        foreach ($candidates as $candidate) {
            $pairKey = $candidate->supplierId . ':' . $candidate->productId;
            if (isset($seenPairs[$pairKey])) {
                continue;
            }

            $seenPairs[$pairKey] = true;

            $quote = QuoteEntity::hydrate(
                id: Uuid::v7(),
                bookingId: Uuid::fromString($bookingId),
                supplierId: Uuid::fromString($candidate->supplierId),
                productId: Uuid::fromString($candidate->productId),
                price: $candidate->price,
                createdAt: $createdAt,
            );

            $this->quoteWriteRepository->save($quote);
        }
    }
}
