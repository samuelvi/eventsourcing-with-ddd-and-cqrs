<?php

declare(strict_types=1);

namespace App\Application\Handler\Quotes;

use App\Domain\Derivation\Candidate\QuoteCandidate;
use App\Domain\Derivation\DerivationRuleEngine;
use App\Domain\Derivation\Facts\BookingFacts;

final readonly class QuoteCandidatesFilter
{
    public function __construct(
        private DerivationRuleEngine $derivationRuleEngine,
    ) {}

    /**
     * @param array<int, QuoteCandidate> $candidates
     * @return array<int, QuoteCandidate>
     */
    public function eligibleForBooking(BookingFacts $bookingFacts, array $candidates): array
    {
        return array_values(array_filter(
            $candidates,
            fn (QuoteCandidate $candidate): bool => $this->derivationRuleEngine->allows(
                $candidate->toDerivationContext($bookingFacts)
            )
        ));
    }
}
