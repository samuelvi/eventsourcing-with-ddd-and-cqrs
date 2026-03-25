<?php

declare(strict_types=1);

namespace App\Application\Command\Quotes;

use App\Application\Quotes\Derivation\Candidate\QuoteCandidateSelectionCriteria;
use App\Application\Service\DerivationRunContext;
use App\Domain\ValueObject\UuidString;

final readonly class GenerateQuotesCommand
{
    /**
     * @param array<int, string> $supplierIds
     * @param array<int, string> $productIds
     * @param array<int, string> $excludedProductIds
     */
    public function __construct(
        public string $bookingId,
        public string $correlationId,
        public array $supplierIds = [],
        public array $productIds = [],
        public array $excludedProductIds = [],
    ) {}

    public function bookingIdVO(): UuidString
    {
        return UuidString::fromString($this->bookingId);
    }

    public function context(): DerivationRunContext
    {
        return new DerivationRunContext(
            bookingId: $this->bookingId,
            correlationId: $this->correlationId,
        );
    }

    public function candidateSelectionCriteria(): QuoteCandidateSelectionCriteria
    {
        return new QuoteCandidateSelectionCriteria(
            supplierIds: $this->supplierIds,
            productIds: $this->productIds,
            excludedProductIds: $this->excludedProductIds,
        );
    }
}
