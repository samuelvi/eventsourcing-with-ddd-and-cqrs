<?php

declare(strict_types=1);

namespace App\Application\Quotes\Derivation\Candidate;

use App\Domain\Derivation\Candidate\QuoteCandidate;
use App\Domain\Derivation\Facts\BookingFacts;
use App\Domain\Repository\ProductReadRepositoryInterface;

final readonly class QuoteCandidateFinderHandler
{
    public function __construct(
        private ProductReadRepositoryInterface $productReadRepository,
    ) {}

    /**
     * @return array<int, QuoteCandidate>
     */
    public function findFor(BookingFacts $bookingFacts): array
    {
        $candidatesData = $this->productReadRepository->findCandidatesFirstFilter(
            budget: $bookingFacts->budget,
            bookingId: $bookingFacts->bookingId,
            country: $bookingFacts->country,
        );

        $candidates = array_map(
            static fn(array $row): QuoteCandidate => QuoteCandidate::fromRow($row),
            $candidatesData,
        );

        return $candidates;
    }
}
