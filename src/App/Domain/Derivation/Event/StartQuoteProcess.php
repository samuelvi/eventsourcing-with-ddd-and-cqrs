<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Event;

final readonly class StartQuoteProcess
{
    public function __construct(
        public string $correlationId,
        public string $bookingId,
        public \DateTimeImmutable $occurredOn = new \DateTimeImmutable(),
    ) {}
}
