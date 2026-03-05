<?php

declare(strict_types=1);

namespace App\Application\Handler\Quotes;

use App\Application\Command\Quotes\GenerateQuotesCommand;
use App\Domain\Derivation\Candidate\QuoteCandidate;
use App\Domain\Derivation\Event\QuoteCreated;
use App\Domain\Derivation\Event\QuoteFlowFinsih;
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
    private const QUOTE_LIMIT = 4;

    public function __construct(
        private DerivationContextBuilder       $contextBuilder,
        private ProductReadRepositoryInterface $productReadRepository,
        private QuoteCandidatesFilter          $quoteCandidatesFilter,
        private QuoteWriteRepositoryInterface  $quoteWriteRepository,
        private DerivationEventPublisher       $eventPublisher,
    )
    {
    }


    public function __invoke(GenerateQuotesCommand $command): void
    {

        $bookingId = $command->bookingIdVO()->toString();
        $bookingFacts = $this->contextBuilder->buildForBooking($bookingId);

        if ($bookingFacts === null) {
            $this->eventPublisher->publishFlowFinished(new QuoteFlowFinsih(
                bookingId: $bookingId,
                correlationId: $command->correlationId,
                lastEvent: null,
                occurredOn: new DateTimeImmutable(),
            ));
            return;
        }

        $candidates = $this->findCandidates($bookingFacts);

        if ($candidates === []) {
            $this->eventPublisher->publishFlowFinished(new QuoteFlowFinsih(
                bookingId: $bookingId,
                correlationId: $command->correlationId,
                lastEvent: null,
                occurredOn: new DateTimeImmutable(),
            ));
            return;
        }

        $this->eventPublisher->publishLimited(new QuoteLimited(
            correlationId: $command->correlationId,
            bookingId: $bookingId,
            limit: self::QUOTE_LIMIT,
            totalCandidates: count($candidates),
            selected: false,
        ));


        $eligibleCandidates = $this->quoteCandidatesFilter->eligibleForBooking($bookingFacts, $candidates);
        $rankedCandidates = $this->rankAndLimitCandidates($eligibleCandidates);

        $this->eventPublisher->publishLimitedByRules(new QuoteLimitedByRules(
            correlationId: $command->correlationId,
            bookingId: $bookingId,
            limit: self::QUOTE_LIMIT,
            totalAfterRules: count($eligibleCandidates),
            totalCandidates: count($rankedCandidates),
            selected: false,
        ));

        if ($rankedCandidates === []) {
            return;
        }

        //Almaceno las quotes
        $this->saveQuotes($bookingId, $rankedCandidates, $command->correlationId);
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
            static fn(array $row): QuoteCandidate => QuoteCandidate::fromRow($row),
            $candidatesData
        );
    }

    /**
     * @param array<int, QuoteCandidate> $candidates
     * @return array<int, QuoteCandidate>
     */
    private function rankAndLimitCandidates(array $candidates): array
    {
        usort(
            $candidates,
            static function (QuoteCandidate $left, QuoteCandidate $right): int {
                if ($left->price !== $right->price) {
                    return $right->price <=> $left->price;
                }

                $supplierComparison = strcmp($left->supplierId, $right->supplierId);
                if ($supplierComparison !== 0) {
                    return $supplierComparison;
                }

                return strcmp($left->productId, $right->productId);
            }
        );

        return array_slice($candidates, 0, self::QUOTE_LIMIT);
    }


    /**
     * @param array<int, QuoteCandidate> $candidates
     */
    private function saveQuotes(string $bookingId, array $candidates, ?string $correlationId): void
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

            $this->eventPublisher->publishCreated(new QuoteCreated(
                quoteId: $quote->id->toRfc4122(),
                bookingId: $bookingId,
                supplierId: $candidate->supplierId,
                productId: $candidate->productId,
                price: $candidate->price,
                correlationId: $correlationId,
                occurredOn: $createdAt,
            ));
        }
    }
}
