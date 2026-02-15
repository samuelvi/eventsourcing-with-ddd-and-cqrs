<?php

declare(strict_types=1);

namespace App\Domain\Event;

final readonly class BookingWizardCompleted
{
    public function __construct(
        public string $bookingId,
        public string $userId,
        public int $pax,
        public float $budget,
        public string $clientName,
        public string $clientEmail,
        public \DateTimeImmutable $occurredOn
    ) {}

    public static function occur(
        string $bookingId,
        string $userId,
        int $pax,
        float $budget,
        string $clientName,
        string $clientEmail,
        \DateTimeImmutable $occurredOn
    ): self {
        return new self($bookingId, $userId, $pax, $budget, $clientName, $clientEmail, $occurredOn);
    }
}
