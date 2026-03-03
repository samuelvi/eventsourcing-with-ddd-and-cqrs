<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Event;

final readonly class QuoteLimited
{
    public function __construct(
        public string $correlationId,
        public string $bookingId,
        public int $limit,
        public int $totalCandidates,
        public bool $selected,
        public \DateTimeImmutable $occurredOn = new \DateTimeImmutable(),
    ) {}
}
