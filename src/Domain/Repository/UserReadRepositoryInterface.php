<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface UserReadRepositoryInterface
{
    /**
     * @return array<array{id: string, name: string, email: string}>
     */
    public function findAllForList(): array;

    /**
     * @return array{id: string, name: string, email: string}|null
     */
    public function findById(string $id): ?array;

    public function countAll(): int;

    public function existsByEmail(string $email): bool;

    public function exists(string $id): bool;
}
