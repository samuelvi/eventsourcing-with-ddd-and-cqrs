<?php

declare(strict_types=1);

namespace App\Application\Quotes\Derivation\Policy;

use App\Domain\Derivation\Candidate\QuoteCandidate;
use App\Domain\Derivation\Facts\BookingFacts;

interface QuoteEligibilityPolicyInterface
{
    /**
     * @param array<int, QuoteCandidate> $candidates
     * @return array<int, QuoteCandidate>
     */
    public function eligibleFor(BookingFacts $bookingFacts, array $candidates): array;
}
