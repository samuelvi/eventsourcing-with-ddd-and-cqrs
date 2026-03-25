<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\ValueObject\UuidString;
use Symfony\Component\Uid\Uuid;

final readonly class DerivationRunContextFactory
{
    public function create(
        string $bookingId,
        ?string $correlationId = null,
    ): DerivationRunContext {
        return new DerivationRunContext(
            bookingId: UuidString::fromString($bookingId)->toString(),
            correlationId: $this->normalizeCorrelationId($correlationId),
        );
    }

    private function normalizeCorrelationId(?string $correlationId): string
    {
        if ($correlationId === null || trim($correlationId) === '') {
            return Uuid::v7()->toRfc4122();
        }

        return UuidString::fromString($correlationId)->toString();
    }
}
