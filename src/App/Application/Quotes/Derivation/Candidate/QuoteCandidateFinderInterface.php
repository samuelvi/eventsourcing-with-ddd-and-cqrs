<?php

declare(strict_types=1);

namespace App\Application\Quotes\Derivation\Candidate;

use App\Domain\Derivation\Candidate\QuoteCandidate;
use App\Domain\Derivation\Facts\BookingFacts;

interface QuoteCandidateFinderInterface
{
    /**
     * @return array<int, QuoteCandidate>
     */
    public function findFor(BookingFacts $bookingFacts): array;
}
