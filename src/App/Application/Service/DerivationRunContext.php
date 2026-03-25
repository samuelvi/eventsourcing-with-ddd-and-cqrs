<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\ValueObject\UuidString;

final readonly class DerivationRunContext
{
    public string $bookingId;
    public string $correlationId;

    public function __construct(
        string $bookingId,
        string $correlationId,
    ) {
        $this->bookingId = UuidString::fromString($bookingId)->toString();
        $this->correlationId = UuidString::fromString($correlationId)->toString();
    }
}
