<?php

declare(strict_types=1);

namespace App\Application\Quotes\Derivation\Run;

use App\Application\Service\DerivationRunContext;

interface DerivationRunRecorderInterface
{
    public function recordStarted(DerivationRunContext $context): void;

    public function recordNoBookingFacts(DerivationRunContext $context): void;

    public function recordNoCandidates(DerivationRunContext $context): void;

    public function recordCandidatesLoaded(DerivationRunContext $context, int $totalCandidates, int $limit): void;

    public function recordCandidatesSelected(DerivationRunContext $context, int $eligibleCandidates, int $selectedCandidates, int $limit): void;

    public function recordCompleted(DerivationRunContext $context): void;
}
