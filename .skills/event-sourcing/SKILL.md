---
name: event-sourcing
description: Event Sourcing pattern implementation with Domain Events for Symfony applications.
---

# Event Sourcing Pattern

## Overview
Event Sourcing captures all changes to application state as a sequence of events. Instead of storing current state, store the events that led to that state.

## Implementation

### Domain Event Structure
```php
final readonly class EntityCreatedEvent
{
    public function __construct(
        public string $eventId,
        public int $aggregateId,
        public string $name,
        public \DateTimeImmutable $occurredOn,
    ) {}

    public static function create(int $entityId, string $name): self
    {
        return new self(
            eventId: \Symfony\Component\Uid\Uuid::v4()->toRfc4122(),
            aggregateId: $entityId,
            name: $name,
            occurredOn: new \DateTimeImmutable(),
        );
    }
}
```

### Event Store Interface
```php
interface EventStoreInterface
{
    public function append(DomainEventInterface $event): void;
    public function getEventsForAggregate(int $aggregateId): array;
}
```

### Usage in Command Handler
```php
public function __invoke(CreateEntityCommand $command): void
{
    $entity = Entity::create(...);
    $this->repository->save($entity);
    
    // Persist domain event
    $event = EntityCreatedEvent::create($entity->getId(), $entity->getName());
    $this->eventStore->append($event);
}
```

## Key Principles
- Events are immutable and append-only
- State can be rebuilt by replaying events
- Audit trail comes for free
- Time travel debugging possible

## References
- [Event Sourcing by Martin Fowler](https://martinfowler.com/eaaDev/EventSourcing.html)
