<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface BookingReadRepositoryInterface
{
    /**
     * @return array<array<string, mixed>>
     */
    public function findAllForList(): array;

    /**
     * @return array<array<string, mixed>>
     */
    public function findPending(): array;

    public function countAll(): int;

    public function exists(string $id): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array;
}