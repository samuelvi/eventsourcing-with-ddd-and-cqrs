<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\ValueObject\UuidString;

final readonly class DerivationRunContext
{
    public string $bookingId;
    public string $derivationRunId;
    public string $correlationId;

    public function __construct(
        string $bookingId,
        string $derivationRunId,
        string $correlationId,
    ) {
        $this->bookingId = UuidString::fromString($bookingId)->toString();
        $this->derivationRunId = DerivationRunId::fromString($derivationRunId)->toString();
        $this->correlationId = UuidString::fromString($correlationId)->toString();
    }
}
