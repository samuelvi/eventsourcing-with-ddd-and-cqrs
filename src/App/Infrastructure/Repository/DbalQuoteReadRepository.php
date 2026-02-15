<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\QuoteReadRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;

final readonly class DbalQuoteReadRepository implements QuoteReadRepositoryInterface
{
    public function __construct(
        private ReadEntityManager $entityManager,
    ) {}

    /**
     * @return array<array{id: string, booking_id: string, supplier_id: string, status: string, price: float}>
     */
    public function findByBookingId(string $bookingId): array
    {
        $sql = 'SELECT id, booking_id, supplier_id, status, price FROM quotes WHERE booking_id = :bookingId';
        /** @var array<array{id: string, booking_id: string, supplier_id: string, status: string, price: float}> */
        return $this->entityManager->query($sql, ['bookingId' => $bookingId]);
    }

    public function exists(string $id): bool
    {
        $sql = 'SELECT 1 FROM quotes WHERE id = :id LIMIT 1';
        return (bool) $this->entityManager->fetchOne($sql, ['id' => $id]);
    }
}
