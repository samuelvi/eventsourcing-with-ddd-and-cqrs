<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Event;

final readonly class QuoteCandidatesNotFound
{
    public function __construct(
        public string $bookingId,
        public string $correlationId,
        public \DateTimeImmutable $occurredOn = new \DateTimeImmutable(),
    ) {}
}
