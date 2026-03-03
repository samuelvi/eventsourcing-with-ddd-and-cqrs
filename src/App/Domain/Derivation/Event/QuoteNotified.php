<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Event;

final readonly class QuoteNotified
{
    public function __construct(
        public string $quoteId,
        public string $bookingId,
        public string $supplierId,
        public string $notificationMethod,
        public ?string $correlationId = null,
        public \DateTimeImmutable $occurredOn = new \DateTimeImmutable(),
    ) {}
}
