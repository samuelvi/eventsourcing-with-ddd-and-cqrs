<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface BookingReadRepositoryInterface
{
    public function countAll(): int;

    public function exists(string $id): bool;
}
