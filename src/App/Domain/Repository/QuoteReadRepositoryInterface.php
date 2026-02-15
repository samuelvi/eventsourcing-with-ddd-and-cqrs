<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface QuoteReadRepositoryInterface
{
    /**
     * @return array<array{id: string, booking_id: string, supplier_id: string, status: string, price: float}>
     */
    public function findByBookingId(string $bookingId): array;

    public function exists(string $id): bool;
}
