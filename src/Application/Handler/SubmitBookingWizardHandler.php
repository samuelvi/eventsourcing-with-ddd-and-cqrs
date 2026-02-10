<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\SubmitBookingWizardCommand;
use App\Domain\Event\BookingWizardCompleted;
use App\Infrastructure\EventSourcing\StoredEvent;
use App\Infrastructure\EventSourcing\Snapshot;
use App\Domain\Repository\BookingReadRepositoryInterface;
use App\Domain\Repository\UserReadRepositoryInterface;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsMessageHandler]
final readonly class SubmitBookingWizardHandler
{
    public function __construct(
        private UserReadRepositoryInterface $userRepository,
        private BookingReadRepositoryInterface $bookingRepository,
        private MongoStore $mongoStore,
        private MessageBusInterface $eventBus,
        private LockFactory $lockFactory,
        private CacheInterface $cache,
        #[Autowire(env: 'int:SNAPSHOT_THRESHOLD')]
        private int $snapshotThreshold,
    ) {}

    public function __invoke(SubmitBookingWizardCommand $command): void
    {
        $aggregateId = Uuid::fromString($command->id);
        $lock = $this->lockFactory->createLock('booking_init_' . $aggregateId->toRfc4122());

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

            // 1. Create the Domain Event
            $event = BookingWizardCompleted::occur(
                bookingId: $aggregateId->toRfc4122(),
                pax: $command->pax,
                budget: $command->budget,
                clientName: $command->clientName,
                clientEmail: $command->clientEmail,
                occurredOn: $occurredOn
            );

            // 2. Persist to Event Store (Mongo)
            $storedEvent = StoredEvent::commit(
                aggregateId: $aggregateId,
                eventType: BookingWizardCompleted::class,
                payload: [
                    'bookingId' => $aggregateId->toRfc4122(),
                    'pax' => $command->pax,
                    'budget' => $command->budget,
                    'clientName' => $command->clientName,
                    'clientEmail' => $command->clientEmail,
                    'occurredOn' => $occurredOn->format(\DateTimeInterface::ATOM)
                ],
                occurredOn: $occurredOn
            );

            $this->mongoStore->saveEvent($storedEvent);

            // --- AUTOMATIC SNAPSHOT LOGIC ---
            $eventCount = $this->mongoStore->countEvents();
            if ($eventCount > 0 && $eventCount % $this->snapshotThreshold === 0) {
                $userCount = $this->userRepository->countAll();
                $bookingCount = $this->bookingRepository->countAll();

                $snapshot = Snapshot::take(
                    $aggregateId,
                    $eventCount,
                    ['users' => $userCount, 'bookings' => $bookingCount, 'auto' => true]
                );
                $this->mongoStore->saveSnapshot($snapshot);
            }

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