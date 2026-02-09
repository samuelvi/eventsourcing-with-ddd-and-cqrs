<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mongo;

use App\Domain\Model\StoredEvent;
use App\Domain\Model\Snapshot;
use App\Domain\Model\ProjectionCheckpoint;
use Symfony\Component\Uid\Uuid;

final readonly class MongoStore
{
    public function __construct(
        private MongoClient $mongoClient,
    ) {}

    // --- Event Store ---

    public function saveEvent(StoredEvent $event): void
    {
        $this->mongoClient->getDatabase()->selectCollection('events')->insertOne($event->toArray());
    }

    /**
     * @return array<StoredEvent>
     */
    public function findEvents(): array
    {
        $cursor = $this->mongoClient->getDatabase()->selectCollection('events')->find([], ['sort' => ['occurredOn' => -1]]);
        $events = [];
        foreach ($cursor as $doc) {
            $events[] = StoredEvent::fromArray(json_decode((string) json_encode($doc), true));
        }
        return $events;
    }

    public function countEvents(): int
    {
        return $this->mongoClient->getDatabase()->selectCollection('events')->countDocuments();
    }

    public function findEventByAggregateId(Uuid $aggregateId): ?StoredEvent
    {
        $doc = $this->mongoClient->getDatabase()->selectCollection('events')->findOne(['aggregateId' => $aggregateId->toRfc4122()]);
        return $doc ? StoredEvent::fromArray(json_decode((string) json_encode($doc), true)) : null;
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
            $snapshots[] = Snapshot::fromArray(json_decode((string) json_encode($doc), true));
        }
        return $snapshots;
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
        return $doc ? ProjectionCheckpoint::fromArray(json_decode((string) json_encode($doc), true)) : null;
    }

    /**
     * @return array<ProjectionCheckpoint>
     */
    public function findAllCheckpoints(): array
    {
        $cursor = $this->mongoClient->getDatabase()->selectCollection('checkpoints')->find();
        $checkpoints = [];
        foreach ($cursor as $doc) {
            $checkpoints[] = ProjectionCheckpoint::fromArray(json_decode((string) json_encode($doc), true));
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
