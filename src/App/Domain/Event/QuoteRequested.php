<?php

declare(strict_types=1);

namespace App\Domain\Event;

use Symfony\Component\Uid\Uuid;

final readonly class QuoteRequested
{
    public function __construct(
        public string $quoteId,
        public string $bookingId,
        public string $supplierId,
        public string $productId,
        public float $requestedPrice,
        public \DateTimeImmutable $occurredOn
    ) {}
}
