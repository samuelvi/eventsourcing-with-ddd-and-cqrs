<?php

declare(strict_types=1);

namespace App\Application\Command\Quotes;

use App\Application\Service\DerivationRunContext;
use App\Application\Service\DerivationRunId;
use App\Domain\ValueObject\UuidString;

final readonly class GenerateQuotesCommand
{
    public function __construct(
        public string $bookingId,
        public string $derivationRunId,
        public string $correlationId,
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
}
