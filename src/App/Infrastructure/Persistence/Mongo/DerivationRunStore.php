<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mongo;

use App\Application\Service\DerivationRun;
use MongoDB\Collection;

final readonly class DerivationRunStore
{
    public function __construct(
        private MongoClient $mongoClient,
    ) {
        $this->ensureIndexes();
    }

    public function open(DerivationRun $derivationRun): void
    {
        $this->collection()->updateOne(
            ['derivationRunId' => $derivationRun->derivationRunId],
            ['$setOnInsert' => $derivationRun->toArray()],
            ['upsert' => true],
        );
    }

    /**
     * @return array<int, DerivationRun>
     */
    public function findByBookingId(string $bookingId): array
    {
        $cursor = $this->collection()->find(
            ['bookingId' => $bookingId],
            ['sort' => ['openedAt' => -1]],
        );

        $runs = [];
        foreach ($cursor as $doc) {
            $runs[] = DerivationRun::fromArray($this->toArrayFromDoc($doc));
        }

        return $runs;
    }

    private function ensureIndexes(): void
    {
        $collection = $this->collection();
        $collection->createIndex(['derivationRunId' => 1], ['unique' => true]);
        $collection->createIndex(['bookingId' => 1, 'openedAt' => -1]);
        $collection->createIndex(['correlationId' => 1]);
    }

    private function collection(): Collection
    {
        return $this->mongoClient->getDatabase()->selectCollection('derivation_runs');
    }

    /**
     * @param object|array<string, mixed>|null $doc
     * @return array<string, mixed>
     */
    private function toArrayFromDoc(object|array|null $doc): array
    {
        if ($doc === null) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) json_encode($doc), true);

        return $decoded;
    }
}
