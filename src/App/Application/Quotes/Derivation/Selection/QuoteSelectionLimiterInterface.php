<?php

declare(strict_types=1);

namespace App\Application\Quotes\Derivation\Selection;

use App\Domain\Derivation\Candidate\QuoteCandidate;

interface QuoteSelectionLimiterInterface
{
    /**
     * @param array<int, QuoteCandidate> $candidates
     * @return array<int, QuoteCandidate>
     */
    public function limit(array $candidates): array;

    public function quoteLimit(): int;
}
