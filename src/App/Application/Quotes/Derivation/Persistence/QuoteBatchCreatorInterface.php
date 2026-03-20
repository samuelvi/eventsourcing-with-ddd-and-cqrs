<?php

declare(strict_types=1);

namespace App\Application\Quotes\Derivation\Persistence;

use App\Application\Service\DerivationRunContext;
use App\Domain\Derivation\Candidate\QuoteCandidate;

interface QuoteBatchCreatorInterface
{
    /**
     * @param array<int, QuoteCandidate> $candidates
     * @return array<int, string>
     */
    public function create(DerivationRunContext $context, array $candidates): array;
}
