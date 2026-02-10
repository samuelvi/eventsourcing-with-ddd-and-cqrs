<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\CreateUserCommand;
use App\Domain\Event\UserRegistered;
use App\Infrastructure\EventSourcing\StoredEvent;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Cache\CacheInterface;

#[AsMessageHandler]
final readonly class CreateUserHandler
{
    public function __construct(
        private MongoStore $mongoStore,
        private MessageBusInterface $eventBus,
        private LockFactory $lockFactory,
        private CacheInterface $cache,
    ) {}

    public function __invoke(CreateUserCommand $command): void
    {
        $aggregateId = Uuid::fromString($command->id);
        $lock = $this->lockFactory->createLock('user_creation_' . $aggregateId->toRfc4122());

        if (!$lock->acquire(true)) {
            return;
        }

        try {
            // Idempotency check in Mongo
            $exists = $this->mongoStore->findEventByAggregateId($aggregateId);

            if ($exists) {
                return;
            }

            $occurredOn = new \DateTimeImmutable();

            $email = strtolower(trim($command->email));

            // 1. Create the Domain Event
            $event = new UserRegistered(
                userId: $aggregateId->toRfc4122(),
                name: $command->name,
                email: $email,
                occurredOn: $occurredOn
            );

            // 2. Persist to Event Store (Mongo)
            $storedEvent = StoredEvent::commit(
                aggregateId: $aggregateId,
                eventType: UserRegistered::class,
                payload: [
                    'userId' => $aggregateId->toRfc4122(),
                    'name' => $command->name,
                    'email' => $email,
                    'occurredOn' => $occurredOn->format(\DateTimeInterface::ATOM)
                ],
                occurredOn: $occurredOn
            );

            $this->mongoStore->saveEvent($storedEvent);

            // 3. Dispatch to Async Bus (for Projections)
            $projectionsEnabled = $this->cache->get('demo_projections_enabled', fn() => true);
            if ($projectionsEnabled) {
                $this->eventBus->dispatch($event);
            }
        } finally {
            $lock->release();
        }
    }
}
