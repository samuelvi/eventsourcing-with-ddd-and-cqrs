<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Facts;

final readonly class BookingFacts
{
    public function __construct(
        public string $bookingId,
        public ?string $country,
        public float $budget,
    ) {}
}
