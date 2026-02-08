<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\BookingEntity;

interface BookingWriteRepositoryInterface
{
    public function save(BookingEntity $booking): void;
}
