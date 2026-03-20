<?php

declare(strict_types=1);

namespace App\Application\Quotes\Derivation\Candidate;

use App\Domain\Derivation\Candidate\QuoteCandidate;
use App\Domain\Derivation\Facts\BookingFacts;
use App\Domain\Repository\ProductReadRepositoryInterface;

final readonly class QuoteCandidateFinder implements QuoteCandidateFinderInterface
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
            $bookingFacts->budget,
            $bookingFacts->country,
        );

        return array_map(
            static fn(array $row): QuoteCandidate => QuoteCandidate::fromRow($row),
            $candidatesData,
        );
    }
}
