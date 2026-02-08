<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Model\BookingEntity;
use App\Domain\Repository\BookingWriteRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\WriteEntityManager;

final readonly class DoctrineBookingWriteRepository implements BookingWriteRepositoryInterface
{
    public function __construct(
        private WriteEntityManager $entityManager,
    ) {}

    public function save(BookingEntity $booking): void
    {
        $this->entityManager->persist($booking);
        $this->entityManager->flush();
    }
}
