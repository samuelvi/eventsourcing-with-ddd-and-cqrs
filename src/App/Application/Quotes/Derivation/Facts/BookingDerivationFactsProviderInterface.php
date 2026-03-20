<?php

declare(strict_types=1);

namespace App\Application\Quotes\Derivation\Facts;

use App\Domain\Derivation\Facts\BookingFacts;

interface BookingDerivationFactsProviderInterface
{
    public function forBookingId(string $bookingId): ?BookingFacts;
}
