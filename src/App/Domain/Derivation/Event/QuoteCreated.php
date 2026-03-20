<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Event;

final readonly class QuoteCreated
{
    public function __construct(
        public string $quoteId,
        public string $bookingId,
        public string $derivationRunId,
        public string $supplierId,
        public string $productId,
        public float $price,
        public ?string $correlationId = null,
        public \DateTimeImmutable $occurredOn = new \DateTimeImmutable(),
    ) {}
}
