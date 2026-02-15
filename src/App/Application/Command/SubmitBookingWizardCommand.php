<?php

declare(strict_types=1);

namespace App\Application\Command;

final readonly class SubmitBookingWizardCommand
{
    private function __construct(
        public string $id,
        public int $pax,
        public float $budget,
        public string $clientName,
        public string $clientEmail,
    ) {}

    public static function create(
        string $id,
        int $pax,
        float $budget,
        string $clientName,
        string $clientEmail
    ): self {
        return new self($id, $pax, $budget, $clientName, $clientEmail);
    }
}
