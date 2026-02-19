<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Model\AggregateRootInterface;
use App\Domain\Repository\EventStoreRepositoryInterface;
use App\Domain\Shared\TypeAssert;
use App\Infrastructure\EventSourcing\StoredEvent;
use App\Infrastructure\EventSourcing\Snapshot as SnapshotEntity;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @template T of AggregateRootInterface
 * @implements EventStoreRepositoryInterface<T>
 */
readonly class EventSourcingRepository implements EventStoreRepositoryInterface
{
    /**
     * @param class-string<T> $aggregateClass
     */
    public function __construct(
        private string $aggregateClass,
        private MongoStore $mongoStore,
        private SerializerInterface $serializer,
        private int $snapshotThreshold,
    ) {}

    /**
     * @param T $aggregate
     */
    public function save(AggregateRootInterface $aggregate): void
    {
        $events = $aggregate->getRecordedEvents();
        $version = $aggregate->getVersion() - count($events);

        foreach ($events as $event) {
            $version++;
            $storedEvent = StoredEvent::commit(
                aggregateId: $aggregate->getAggregateId(),
                eventType: get_class($event),
                payload: TypeAssert::array(json_decode($this->serializer->serialize($event, 'json'), true)),
                version: $version
            );

            $this->mongoStore->saveEvent($storedEvent);
        }

        $shouldSnapshotByAggregateVersion =
            $aggregate->getVersion() >= $this->snapshotThreshold
            && $aggregate->getVersion() % $this->snapshotThreshold === 0;

        if ($shouldSnapshotByAggregateVersion) {
            $snapshot = SnapshotEntity::take(
                $aggregate->getAggregateId(),
                $aggregate->getVersion(),
                $aggregate->getSnapshotState()
            );
            $this->mongoStore->saveSnapshot($snapshot);
        }

        $aggregate->clearRecordedEvents();
    }

    /**
     * @return T|null
     */
    public function get(Uuid $id): ?AggregateRootInterface
    {
        /** @var class-string<T> $class */
        $class = $this->aggregateClass;

        // 1. Try to load the latest snapshot
        $snapshot = $this->mongoStore->findLatestSnapshotByAggregateId($id);
        
        $aggregate = null;
        $lastVersion = 0;

        if ($snapshot) {
            $aggregate = $class::reconstituteFromSnapshot(
                $id,
                $snapshot->version,
                $snapshot->state
            );
            $lastVersion = $snapshot->version;
            
            // 2. Load events after the snapshot version
            $storedEvents = $this->mongoStore->findAllEventsAfterVersion($id, $lastVersion);
        } else {
            // No snapshot: Load all events
            $storedEvents = $this->mongoStore->findAllEventsByAggregateId($id);
        }

        if (empty($storedEvents) && $aggregate === null) {
            return null;
        }

        $domainEvents = [];
        foreach ($storedEvents as $storedEvent) {
            $domainEvents[] = $this->serializer->deserialize(
                json_encode($storedEvent->payload, JSON_THROW_ON_ERROR),
                $storedEvent->eventType,
                'json'
            );
        }

        if ($aggregate) {
            // Apply remaining events to the snapshotted aggregate
            foreach ($domainEvents as $event) {
                // We need a way to apply events to an existing aggregate instance
                // Re-using reconstituteFromHistory logic but on an existing object
                // Let's add an internal method or just do it here if possible.
                // Since apply is protected, we can't call it from here easily without a public bridge.
                // But wait, reconstituteFromHistory is static.
            }
            // For simplicity in this POC, if there are events after snapshot, 
            // we just reconstitute from history using all events if it's easier, 
            // OR we fix the AggregateRoot to allow applying a batch of events.
            
            // Better: Let's add a public method to AggregateRoot to apply multiple events.
        }

        return $class::reconstituteFromHistory($id, $domainEvents, $aggregate);
    }
}
