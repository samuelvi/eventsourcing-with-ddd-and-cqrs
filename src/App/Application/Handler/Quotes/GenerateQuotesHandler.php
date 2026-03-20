<?php

declare(strict_types=1);

namespace App\Application\Handler\Quotes;

use App\Application\Command\Quotes\GenerateQuotesCommand;
use App\Application\Quotes\Derivation\Candidate\QuoteCandidateFinderInterface;
use App\Application\Quotes\Derivation\Facts\BookingDerivationFactsProviderInterface;
use App\Application\Quotes\Derivation\Persistence\QuoteBatchCreatorInterface;
use App\Application\Quotes\Derivation\Policy\QuoteEligibilityPolicyInterface;
use App\Application\Quotes\Derivation\Process\SupplierProcessStarterInterface;
use App\Application\Quotes\Derivation\Ranking\QuoteRankingStrategyInterface;
use App\Application\Quotes\Derivation\Run\DerivationRunRecorderInterface;
use App\Application\Quotes\Derivation\Selection\QuoteSelectionLimiterInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GenerateQuotesHandler
{
    public function __construct(
        private BookingDerivationFactsProviderInterface $bookingFactsProvider,
        private QuoteCandidateFinderInterface $quoteCandidateFinder,
        private QuoteEligibilityPolicyInterface $quoteEligibilityPolicy,
        private QuoteRankingStrategyInterface $quoteRankingStrategy,
        private QuoteSelectionLimiterInterface $quoteSelectionLimiter,
        private QuoteBatchCreatorInterface $quoteBatchCreator,
        private SupplierProcessStarterInterface $supplierProcessStarter,
        private DerivationRunRecorderInterface $derivationRunRecorder,
    )
    {
    }


    public function __invoke(GenerateQuotesCommand $command): void
    {
        $context = $command->context();
        $selectionCriteria = $command->candidateSelectionCriteria();
        $this->derivationRunRecorder->recordStarted($context);

        $bookingFacts = $this->bookingFactsProvider->forBookingId($context->bookingId);

        if ($bookingFacts === null) {
            $this->derivationRunRecorder->recordNoBookingFacts($context);
            return;
        }

        $candidates = $this->quoteCandidateFinder->findFor($bookingFacts, $selectionCriteria);

        if ($candidates === []) {
            $this->derivationRunRecorder->recordNoCandidates($context);
            return;
        }

        $quoteLimit = $this->quoteSelectionLimiter->quoteLimit();
        $this->derivationRunRecorder->recordCandidatesLoaded($context, count($candidates), $quoteLimit);

        $eligibleCandidates = $this->quoteEligibilityPolicy->eligibleFor($bookingFacts, $candidates);
        $rankedCandidates = $this->quoteRankingStrategy->rank($eligibleCandidates);
        $selectedCandidates = $this->quoteSelectionLimiter->limit($rankedCandidates);

        $this->derivationRunRecorder->recordCandidatesSelected(
            $context,
            count($eligibleCandidates),
            count($selectedCandidates),
            $quoteLimit,
        );

        $quoteIds = $this->quoteBatchCreator->create($context, $selectedCandidates);

        $this->supplierProcessStarter->start($context, $quoteIds);
        $this->derivationRunRecorder->recordCompleted($context);
    }
}
