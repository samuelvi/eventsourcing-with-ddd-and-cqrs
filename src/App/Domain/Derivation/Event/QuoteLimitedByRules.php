<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Event;

final readonly class QuoteLimitedByRules
{
    public function __construct(
        public string $correlationId,
        public string $bookingId,
        public int $limit,
        public int $totalAfterRules,
        public int $totalCandidates,
        public bool $selected,
        public \DateTimeImmutable $occurredOn = new \DateTimeImmutable(),
    ) {}
}
