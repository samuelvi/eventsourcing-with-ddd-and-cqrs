<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface MenuReadRepositoryInterface
{
    public function findById(string $id): ?array;
}
