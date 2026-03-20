<?php

declare(strict_types=1);

namespace App\Application\Quotes\Derivation\Ranking;

use App\Domain\Derivation\Candidate\QuoteCandidate;

interface QuoteRankingStrategyInterface
{
    /**
     * @param array<int, QuoteCandidate> $candidates
     * @return array<int, QuoteCandidate>
     */
    public function rank(array $candidates): array;
}
