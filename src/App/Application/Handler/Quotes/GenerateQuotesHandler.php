<?php

declare(strict_types=1);

namespace App\Application\Handler\Quotes;

use App\Application\Command\Quotes\GenerateQuotesCommand;
use App\Application\Quotes\Derivation\Candidate\QuoteCandidateFinderHandler;
use App\Application\Quotes\Derivation\Facts\BookingDerivationFactsHandler;
use App\Application\Quotes\Derivation\Persistence\QuoteBatchCreatorHandler;
use App\Application\Quotes\Derivation\Policy\QuoteEligibilityHandler;
use App\Application\Quotes\Derivation\Process\SupplierProcessStarterHandler;
use App\Application\Quotes\Derivation\Ranking\QuoteRankingHandler;
use App\Application\Quotes\Derivation\Run\DerivationRunRecorderHandler;
use App\Application\Quotes\Derivation\Selection\QuoteSelectionHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GenerateQuotesHandler
{
    public function __construct(
        private BookingDerivationFactsHandler $bookingFactsHandler,
        private QuoteCandidateFinderHandler $quoteCandidateFinderHandler,
        private QuoteEligibilityHandler $quoteEligibilityHandler,
        private QuoteRankingHandler $quoteRankingHandler,
        private QuoteSelectionHandler $quoteSelectionHandler,
        private QuoteBatchCreatorHandler $quoteBatchCreatorHandler,
        private SupplierProcessStarterHandler $supplierProcessStarterHandler,
        private DerivationRunRecorderHandler $derivationRunRecorderHandler,
        private ?LoggerInterface $n8nLogger = null,
    )
    {
    }


    public function __invoke(GenerateQuotesCommand $command): void
    {
        $context = $command->context();
        $this->derivationRunRecorderHandler->recordStarted($context);

        $bookingFacts = $this->bookingFactsHandler->forBookingId($context->bookingId);

        if ($bookingFacts === null) {
            $this->derivationRunRecorderHandler->recordNoBookingFacts($context);
            return;
        }

        $this->n8nLogger?->info('GenerateQuotesCommand first-filter query by bookingId', [
            'bookingId' => $context->bookingId,
            'correlationId' => $context->correlationId,
        ]);

        $candidates = $this->quoteCandidateFinderHandler->findFor($bookingFacts);

        if ($candidates === []) {
            $this->derivationRunRecorderHandler->recordNoCandidates($context);
            return;
        }

        $quoteLimit = $this->quoteSelectionHandler->quoteLimit();
        $this->derivationRunRecorderHandler->recordCandidatesLoaded($context, count($candidates), $quoteLimit);

        $eligibleCandidates = $this->quoteEligibilityHandler->eligibleFor($bookingFacts, $candidates);
        $rankedCandidates = $this->quoteRankingHandler->rank($eligibleCandidates);
        $selectedCandidates = $this->quoteSelectionHandler->limit($rankedCandidates);

        $this->derivationRunRecorderHandler->recordCandidatesSelected(
            $context,
            count($eligibleCandidates),
            count($selectedCandidates),
            $quoteLimit,
        );

        $quoteIds = $this->quoteBatchCreatorHandler->create($context, $selectedCandidates);

        $this->supplierProcessStarterHandler->start($context, $quoteIds);
        $this->derivationRunRecorderHandler->recordCompleted($context);
    }
}
