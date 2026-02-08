<?php

declare(strict_types=1);

namespace App\Domain\Event;

final readonly class BookingWizardCompleted
{
    public function __construct(
        public string $bookingId,
        public int $pax,
        public float $budget,
        public string $clientName,
        public string $clientEmail,
        public \DateTimeImmutable $occurredOn
    ) {}
}
