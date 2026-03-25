<?php

declare(strict_types=1);

namespace App\Application\Quotes\Derivation\Run;

use App\Application\Logger\AppLogger;
use App\Application\Service\DerivationRun;
use App\Application\Service\DerivationRunContext;
use App\Application\Service\DerivationRunTracker;
use App\Domain\Derivation\Event\QuoteCandidatesNotFound;
use App\Domain\Derivation\Event\QuoteFlowFinsih;
use App\Domain\Derivation\Event\QuoteLimited;
use App\Domain\Derivation\Event\QuoteLimitedByRules;
use DateTimeImmutable;

final readonly class DerivationRunRecorderHandler
{
    public function __construct(
        private DerivationRunTracker $derivationRunTracker,
        private DerivationEventPublisherHandler $eventPublisher,
        private AppLogger $appLogger,
    ) {}

    public function recordStarted(DerivationRunContext $context): void
    {
        $this->derivationRunTracker->open($context);
        $this->derivationRunTracker->updateStatus($context->bookingId, $context->correlationId, DerivationRun::STATUS_PROCESSING);
    }

    public function recordNoBookingFacts(DerivationRunContext $context): void
    {
        $this->eventPublisher->publishFlowFinished(new QuoteFlowFinsih(
            bookingId: $context->bookingId,
            correlationId: $context->correlationId,
            lastEvent: 'booking facts null',
            occurredOn: new DateTimeImmutable(),
        ));

        $this->derivationRunTracker->updateStatus($context->bookingId, $context->correlationId, DerivationRun::STATUS_COMPLETED_NO_BOOKING_FACTS);
    }

    public function recordNoCandidates(DerivationRunContext $context): void
    {
        $this->eventPublisher->publishCandidatesNotFound(new QuoteCandidatesNotFound(
            bookingId: $context->bookingId,
            correlationId: $context->correlationId,
            occurredOn: new DateTimeImmutable(),
        ));

        $this->eventPublisher->publishFlowFinished(new QuoteFlowFinsih(
            bookingId: $context->bookingId,
            correlationId: $context->correlationId,
            lastEvent: 'candidates null',
            occurredOn: new DateTimeImmutable(),
        ));

        $this->appLogger->error('No quote candidates found for derivation run', [
            'bookingId' => $context->bookingId,
            'correlationId' => $context->correlationId,
        ]);

        $this->derivationRunTracker->updateStatus($context->bookingId, $context->correlationId, DerivationRun::STATUS_COMPLETED_NO_CANDIDATES);
    }

    public function recordCandidatesLoaded(DerivationRunContext $context, int $totalCandidates, int $limit): void
    {
        $this->eventPublisher->publishLimited(new QuoteLimited(
            correlationId: $context->correlationId,
            bookingId: $context->bookingId,
            limit: $limit,
            totalCandidates: $totalCandidates,
            selected: false,
        ));
    }

    public function recordCandidatesSelected(DerivationRunContext $context, int $eligibleCandidates, int $selectedCandidates, int $limit): void
    {
        $this->eventPublisher->publishLimitedByRules(new QuoteLimitedByRules(
            correlationId: $context->correlationId,
            bookingId: $context->bookingId,
            limit: $limit,
            totalAfterRules: $eligibleCandidates,
            totalCandidates: $selectedCandidates,
            selected: false,
        ));
    }

    public function recordCompleted(DerivationRunContext $context): void
    {
        $this->derivationRunTracker->updateStatus($context->bookingId, $context->correlationId, DerivationRun::STATUS_COMPLETED);
    }
}
