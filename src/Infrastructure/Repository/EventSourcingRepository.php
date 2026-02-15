<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Model\AggregateRootInterface;
use App\Domain\Repository\EventStoreRepositoryInterface;
use App\Infrastructure\EventSourcing\StoredEvent;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @template T of AggregateRootInterface
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
        private MessageBusInterface $eventBus,
    ) {}

    public function save(AggregateRootInterface $aggregate): void
    {
        $events = $aggregate->getRecordedEvents();
        $version = $aggregate->getVersion() - count($events);

        foreach ($events as $event) {
            $version++;
            $storedEvent = StoredEvent::commit(
                aggregateId: $aggregate->getAggregateId(),
                eventType: get_class($event),
                payload: json_decode($this->serializer->serialize($event, 'json'), true),
                version: $version
            );

            $this->mongoStore->saveEvent($storedEvent);
        }

        $aggregate->clearRecordedEvents();
    }

    public function get(Uuid $id): ?AggregateRootInterface
    {
        $storedEvents = $this->mongoStore->findAllEventsByAggregateId($id);

        if (empty($storedEvents)) {
            return null;
        }

        $domainEvents = [];
        foreach ($storedEvents as $storedEvent) {
            $domainEvents[] = $this->serializer->deserialize(
                json_encode($storedEvent->payload),
                $storedEvent->eventType,
                'json'
            );
        }

        /** @var class-string<AggregateRootInterface> $class */
        $class = $this->aggregateClass;
        
        return $class::reconstituteFromHistory($id, $domainEvents);
    }
}
