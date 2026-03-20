<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\ValueObject\UuidString;
use App\Infrastructure\Persistence\Mongo\DerivationRunStore;

final readonly class DerivationRunTracker
{
    public function __construct(
        private DerivationRunStore $derivationRunStore,
    ) {}

    public function open(DerivationRunContext $context): void
    {
        $this->derivationRunStore->open(DerivationRun::open($context));
    }

    public function updateStatus(string $derivationRunId, string $status): void
    {
        $this->derivationRunStore->updateStatus(
            DerivationRunId::fromString($derivationRunId)->toString(),
            $status,
            new \DateTimeImmutable(),
        );
    }

    /**
     * @return array<int, DerivationRun>
     */
    public function findByBookingId(string $bookingId): array
    {
        return $this->derivationRunStore->findByBookingId(
            UuidString::fromString($bookingId)->toString(),
        );
    }
}
