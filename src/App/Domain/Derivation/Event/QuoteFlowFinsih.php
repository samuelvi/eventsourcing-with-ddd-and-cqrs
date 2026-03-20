<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Event;

final readonly class QuoteFlowFinsih
{
    public function __construct(
        public string $bookingId,
        public string $derivationRunId,
        public string $correlationId,
        public ?string $lastEvent = null,
        public \DateTimeImmutable $occurredOn = new \DateTimeImmutable(),
    ) {}
}
