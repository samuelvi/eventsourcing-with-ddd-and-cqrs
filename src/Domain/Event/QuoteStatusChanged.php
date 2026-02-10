<?php

declare(strict_types=1);

namespace App\Domain\Event;

final readonly class QuoteStatusChanged
{
    public function __construct(
        public string $quoteId,
        public string $newStatus,
        public \DateTimeImmutable $occurredOn
    ) {}
}
