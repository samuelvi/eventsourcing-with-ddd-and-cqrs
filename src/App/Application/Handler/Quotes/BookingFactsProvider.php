<?php

declare(strict_types=1);

namespace App\Application\Handler\Quotes;

use App\Domain\Derivation\Facts\BookingFacts;
use App\Domain\Repository\BookingReadRepositoryInterface;
use App\Domain\Shared\TypeAssert;
use App\Domain\ValueObject\NonNegativeAmount;

final readonly class BookingFactsProvider
{
    public function __construct(
        private BookingReadRepositoryInterface $bookingReadRepository,
    ) {}

    public function forBookingId(string $bookingId): ?BookingFacts
    {
        $bookingRow = $this->bookingReadRepository->findById($bookingId);
        if (!$bookingRow) {
            return null;
        }

        /** @var array<string, mixed> $bookingData */
        $bookingData = TypeAssert::array(json_decode(TypeAssert::string($bookingRow['data']), true));
        $budget = NonNegativeAmount::fromFloat(TypeAssert::float($bookingData['budget'] ?? 0.0));
        $bookingCountry = isset($bookingData['country'])
            ? TypeAssert::string($bookingData['country'])
            : (isset($bookingRow['country']) ? TypeAssert::string($bookingRow['country']) : null);

        return new BookingFacts(
            bookingId: $bookingId,
            country: $bookingCountry,
            budget: $budget->toFloat(),
        );
    }
}
