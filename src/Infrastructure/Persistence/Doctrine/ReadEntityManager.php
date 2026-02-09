<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Connection;

final readonly class ReadEntityManager
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @param string $sql
     * @param array<string, mixed> $params
     * @return array<array<string, mixed>>
     */
    public function query(string $sql, array $params = []): array
    {
        return $this->connection->executeQuery($sql, $params)->fetchAllAssociative();
    }

    /**
     * @param string $sql
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->connection->executeQuery($sql, $params)->fetchAssociative();
        return $result === false ? null : $result;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return (int) $this->connection->executeStatement($sql, $params);
    }
}
