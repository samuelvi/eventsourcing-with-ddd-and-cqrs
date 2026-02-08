<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface ProductReadRepositoryInterface
{
    public function findAllForList(): array;
    public function findById(int $id): ?array;
}
