<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mongo;

use App\Infrastructure\EventSourcing\StoredEvent;
use App\Infrastructure\EventSourcing\Snapshot;
use App\Infrastructure\EventSourcing\ProjectionCheckpoint;
use App\Domain\Exception\ConcurrencyException;
use Symfony\Component\Uid\Uuid;
use MongoDB\Driver\Exception\BulkWriteException;

final readonly class MongoStore
{
    public function __construct(
        private MongoClient $mongoClient,
    ) {
        // Ensure indexes (in a real app, this should be a migration command)
        $this->ensureIndexes();
    }

    private function ensureIndexes(): void
    {
        $this->mongoClient->getDatabase()->selectCollection('events')->createIndex(
            ['aggregateId' => 1, 'version' => 1],
            ['unique' => true]
        );
    }

    // --- Event Store ---

    public function saveEvent(StoredEvent $event): void
    {
        try {
            $this->mongoClient->getDatabase()->selectCollection('events')->insertOne($event->toArray());
        } catch (BulkWriteException $e) {
            if ($e->getCode() === 11000) {
                throw ConcurrencyException::versionMismatch(
                    $event->aggregateId->toRfc4122(),
                    $event->version
                );
            }
            throw $e;
        }
    }

    /**
     * @param object|array<string, mixed>|null $doc
     * @return array<string, mixed>
     */
    private function toArrayFromDoc(object|array|null $doc): array
    {
        if (!$doc) {
            return [];
        }
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) json_encode($doc), true);
        return $decoded;
    }

    /**
     * @return array<StoredEvent>
     */
    public function findEvents(): array
    {
        $cursor = $this->mongoClient->getDatabase()->selectCollection('events')->find([], ['sort' => ['occurredOn' => -1]]);
        $events = [];
        foreach ($cursor as $doc) {
            $events[] = StoredEvent::fromArray($this->toArrayFromDoc($doc));
        }
        return $events;
    }

    /**
     * @return array<StoredEvent>
     */
    public function findEventsForReplay(): array
    {
        $events = [];
        foreach ($this->iterateEventsForReplay() as $event) {
            $events[] = $event;
        }

        return $events;
    }

    /**
     * @return iterable<StoredEvent>
     */
    public function iterateEventsForReplay(): iterable
    {
        $cursor = $this->mongoClient->getDatabase()->selectCollection('events')->find(
            [],
            ['sort' => ['occurredOn' => 1, 'aggregateId' => 1, 'version' => 1]]
        );

        foreach ($cursor as $doc) {
            yield StoredEvent::fromArray($this->toArrayFromDoc($doc));
        }
    }

    public function countEvents(): int
    {
        return $this->mongoClient->getDatabase()->selectCollection('events')->countDocuments();
    }

    public function findEventByAggregateId(Uuid $aggregateId): ?StoredEvent
    {
        $doc = $this->mongoClient->getDatabase()->selectCollection('events')->findOne(['aggregateId' => $aggregateId->toRfc4122()]);
        return $doc ? StoredEvent::fromArray($this->toArrayFromDoc($doc)) : null;
    }

    /**
     * @return array<StoredEvent>
     */
    public function findAllEventsByAggregateId(Uuid $aggregateId): array
    {
        $cursor = $this->mongoClient->getDatabase()->selectCollection('events')->find(
            ['aggregateId' => $aggregateId->toRfc4122()],
            ['sort' => ['version' => 1]]
        );
        
        $events = [];
        foreach ($cursor as $doc) {
            $events[] = StoredEvent::fromArray($this->toArrayFromDoc($doc));
        }
        
        return $events;
    }

    /**
     * @return array<StoredEvent>
     */
    public function findAllEventsAfterVersion(Uuid $aggregateId, int $version): array
    {
        $cursor = $this->mongoClient->getDatabase()->selectCollection('events')->find(
            [
                'aggregateId' => $aggregateId->toRfc4122(),
                'version' => ['$gt' => $version]
            ],
            ['sort' => ['version' => 1]]
        );

        $events = [];
        foreach ($cursor as $doc) {
            $events[] = StoredEvent::fromArray($this->toArrayFromDoc($doc));
        }

        return $events;
    }

    // --- Snapshots ---

    public function saveSnapshot(Snapshot $snapshot): void
    {
        $this->mongoClient->getDatabase()->selectCollection('snapshots')->insertOne($snapshot->toArray());
    }

    /**
     * @return array<Snapshot>
     */
    public function findSnapshots(): array
    {
        $cursor = $this->mongoClient->getDatabase()->selectCollection('snapshots')->find([], ['sort' => ['createdAt' => -1]]);
        $snapshots = [];
        foreach ($cursor as $doc) {
            $snapshots[] = Snapshot::fromArray($this->toArrayFromDoc($doc));
        }
        return $snapshots;
    }

    /**
     * Streams only the latest snapshot per aggregate (ordered by aggregateId asc).
     *
     * @return iterable<Snapshot>
     */
    public function iterateLatestSnapshotsByAggregateId(): iterable
    {
        $cursor = $this->mongoClient->getDatabase()->selectCollection('snapshots')->find(
            [],
            ['sort' => ['aggregateId' => 1, 'version' => -1, 'createdAt' => -1]]
        );

        $lastAggregateId = null;
        foreach ($cursor as $doc) {
            $snapshot = Snapshot::fromArray($this->toArrayFromDoc($doc));
            $aggregateId = $snapshot->aggregateId->toRfc4122();
            if ($aggregateId === $lastAggregateId) {
                continue;
            }

            $lastAggregateId = $aggregateId;
            yield $snapshot;
        }
    }

    public function findLatestSnapshotByAggregateId(Uuid $aggregateId): ?Snapshot
    {
        $doc = $this->mongoClient->getDatabase()->selectCollection('snapshots')->findOne(
            ['aggregateId' => $aggregateId->toRfc4122()],
            ['sort' => ['version' => -1]]
        );

        return $doc ? Snapshot::fromArray($this->toArrayFromDoc($doc)) : null;
    }

    public function countSnapshots(): int
    {
        return $this->mongoClient->getDatabase()->selectCollection('snapshots')->countDocuments();
    }

    // --- Checkpoints ---

    public function saveCheckpoint(ProjectionCheckpoint $checkpoint): void
    {
        $this->mongoClient->getDatabase()->selectCollection('checkpoints')->updateOne(
            ['projectionName' => $checkpoint->projectionName],
            ['$set' => $checkpoint->toArray()],
            ['upsert' => true]
        );
    }

    public function findCheckpoint(string $projectionName): ?ProjectionCheckpoint
    {
        $doc = $this->mongoClient->getDatabase()->selectCollection('checkpoints')->findOne(['projectionName' => $projectionName]);
        return $doc ? ProjectionCheckpoint::fromArray($this->toArrayFromDoc($doc)) : null;
    }

    /**
     * @return array<ProjectionCheckpoint>
     */
    public function findAllCheckpoints(): array
    {
        $cursor = $this->mongoClient->getDatabase()->selectCollection('checkpoints')->find();
        $checkpoints = [];
        foreach ($cursor as $doc) {
            $checkpoints[] = ProjectionCheckpoint::fromArray($this->toArrayFromDoc($doc));
        }
        return $checkpoints;
    }

    // --- Utility ---

    public function clearCheckpoints(): void
    {
        $this->mongoClient->getDatabase()->selectCollection('checkpoints')->deleteMany([]);
    }

    public function clearAll(): void
    {
        $this->mongoClient->getDatabase()->selectCollection('events')->deleteMany([]);
        $this->mongoClient->getDatabase()->selectCollection('snapshots')->deleteMany([]);
        $this->mongoClient->getDatabase()->selectCollection('checkpoints')->deleteMany([]);
    }
}
