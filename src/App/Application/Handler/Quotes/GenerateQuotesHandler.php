<?php

declare(strict_types=1);

namespace App\Application\Handler\Quotes;

use App\Application\Command\Quotes\GenerateQuotesCommand;
use App\Application\Service\DerivationRunContext;
use App\Application\Service\DerivationRunTracker;
use App\Domain\Derivation\Candidate\QuoteCandidate;
use App\Domain\Derivation\Event\QuoteCreated;
use App\Domain\Derivation\Event\QuoteFlowFinsih;
use App\Domain\Derivation\Event\QuoteLimited;
use App\Domain\Derivation\Event\QuoteLimitedByRules;
use App\Domain\Derivation\Event\StartQuoteProcess;
use App\Domain\Derivation\Facts\BookingFacts;
use App\Domain\Model\QuoteEntity;
use App\Domain\Repository\ProductReadRepositoryInterface;
use App\Domain\Repository\QuoteWriteRepositoryInterface;
use App\Integrations\N8n\Application\Service\SupplierProcess\QuoteStartedProcess;
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
        private QuoteStartedProcess            $quoteStartedProcess,
        private DerivationRunTracker           $derivationRunTracker,
    )
    {
    }


    public function __invoke(GenerateQuotesCommand $command): void
    {
        $context = $command->context();
        $this->derivationRunTracker->open($context);

        $bookingId = $context->bookingId;
        $derivationRunId = $context->derivationRunId;
        $correlationId = $context->correlationId;
        $bookingFacts = $this->contextBuilder->buildForBooking($bookingId);

        if ($bookingFacts === null) {
            $this->eventPublisher->publishFlowFinished(new QuoteFlowFinsih(
                bookingId: $bookingId,
                derivationRunId: $derivationRunId,
                correlationId: $correlationId,
                lastEvent: 'booking facts null',
                occurredOn: new DateTimeImmutable(),
            ));
            return;
        }

        $candidates = $this->findCandidates($bookingFacts);

        if ($candidates === []) {
            $this->eventPublisher->publishFlowFinished(new QuoteFlowFinsih(
                bookingId: $bookingId,
                derivationRunId: $derivationRunId,
                correlationId: $correlationId,
                lastEvent: 'candidates null',
                occurredOn: new DateTimeImmutable(),
            ));
            return;
        }

        $this->eventPublisher->publishLimited(new QuoteLimited(
            derivationRunId: $derivationRunId,
            correlationId: $correlationId,
            bookingId: $bookingId,
            limit: self::QUOTE_LIMIT,
            totalCandidates: count($candidates),
            selected: false,
        ));


        $eligibleCandidates = $this->quoteCandidatesFilter->eligibleForBooking($bookingFacts, $candidates);
        $rankedCandidates = $this->rankAndLimitCandidates($eligibleCandidates);

        $this->eventPublisher->publishLimitedByRules(new QuoteLimitedByRules(
            derivationRunId: $derivationRunId,
            correlationId: $correlationId,
            bookingId: $bookingId,
            limit: self::QUOTE_LIMIT,
            totalAfterRules: count($eligibleCandidates),
            totalCandidates: count($rankedCandidates),
            selected: false,
        ));


        //Almaceno las quotes
        $quoteIds = $this->saveQuotes($context, $rankedCandidates);

        $this->quoteStartedProcess->notify($context, $quoteIds);
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
     * @return array<int, string>
     */
    private function saveQuotes(DerivationRunContext $context, array $candidates): array
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
                derivationRunId: $context->derivationRunId,
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
