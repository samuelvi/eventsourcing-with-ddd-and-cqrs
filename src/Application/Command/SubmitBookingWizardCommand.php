<?php

declare(strict_types=1);

namespace App\Application\Command;

final readonly class SubmitBookingWizardCommand
{
    public function __construct(
        public string $id,
        public int $pax,
        public float $budget,
        public string $clientName,
        public string $clientEmail,
    ) {}
}
