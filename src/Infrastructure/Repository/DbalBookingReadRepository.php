<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\BookingReadRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use App\Domain\Shared\TypeAssert;

final readonly class DbalBookingReadRepository implements BookingReadRepositoryInterface
{
    public function __construct(
        private ReadEntityManager $entityManager,
    ) {}

    /**
     * @return array<array<string, mixed>>
     */
    public function findAllForList(): array
    {
        $sql = 'SELECT id, created_at, data FROM bookings ORDER BY created_at DESC';
        return $this->entityManager->query($sql);
    }

    public function countAll(): int
    {
        $sql = 'SELECT COUNT(*) FROM bookings';
        $result = $this->entityManager->fetchOne($sql);
        
        return isset($result['count']) ? TypeAssert::int($result['count']) : 0;
    }

    public function exists(string $id): bool
    {
        $sql = 'SELECT 1 FROM bookings WHERE id = :id LIMIT 1';
        return (bool) $this->entityManager->fetchOne($sql, ['id' => $id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        $sql = 'SELECT id, created_at, data FROM bookings WHERE id = :id';
        $result = $this->entityManager->fetchOne($sql, ['id' => $id]);

        return $result ?: null;
    }
}
