<?php

declare(strict_types=1);

namespace App\Application\Handler\Quotes;

use App\Domain\Derivation\Facts\BookingFacts;

final readonly class DerivationContextBuilder
{
    public function __construct(
        private BookingFactsProvider $bookingFactsProvider,
    ) {}

    public function buildForBooking(string $bookingId): ?BookingFacts
    {
        return $this->bookingFactsProvider->forBookingId($bookingId);
    }
}
