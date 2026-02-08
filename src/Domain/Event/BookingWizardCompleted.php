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

    public static function occur(
        string $bookingId,
        int $pax,
        float $budget,
        string $clientName,
        string $clientEmail,
        \DateTimeImmutable $occurredOn
    ): self {
        return new self($bookingId, $pax, $budget, $clientName, $clientEmail, $occurredOn);
    }
}
