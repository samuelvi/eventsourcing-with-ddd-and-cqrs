<?php

declare(strict_types=1);

namespace App\Application\Command\Quotes;

use App\Application\Quotes\Derivation\Candidate\QuoteCandidateSelectionCriteria;
use App\Application\Service\DerivationRunContext;
use App\Application\Service\DerivationRunId;
use App\Domain\ValueObject\UuidString;

final readonly class GenerateQuotesCommand
{
    /**
     * @param array<int, string> $supplierIds
     * @param array<int, string> $productIds
     */
    public function __construct(
        public string $bookingId,
        public string $derivationRunId,
        public string $correlationId,
        public array $supplierIds = [],
        public array $productIds = [],
    ) {}

    public function bookingIdVO(): UuidString
    {
        return UuidString::fromString($this->bookingId);
    }

    public function derivationRunIdVO(): DerivationRunId
    {
        return DerivationRunId::fromString($this->derivationRunId);
    }

    public function context(): DerivationRunContext
    {
        return new DerivationRunContext(
            bookingId: $this->bookingId,
            derivationRunId: $this->derivationRunId,
            correlationId: $this->correlationId,
        );
    }

    public function candidateSelectionCriteria(): QuoteCandidateSelectionCriteria
    {
        return new QuoteCandidateSelectionCriteria(
            supplierIds: $this->supplierIds,
            productIds: $this->productIds,
        );
    }
}
