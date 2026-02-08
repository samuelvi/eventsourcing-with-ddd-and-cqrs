<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Domain\Event\BookingWizardCompleted;
use App\Domain\Model\StoredEvent;
use App\Domain\Model\Snapshot;
use App\Infrastructure\Persistence\Doctrine\WriteEntityManager;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
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
        private WriteEntityManager $entityManager,
        private ReadEntityManager $readEntityManager,
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
            // Idempotency check: Does this aggregate already have events?
            $exists = $this->entityManager->getRepository(StoredEvent::class)
                ->findOneBy(['aggregateId' => $aggregateId]);

            if ($exists) {
                return;
            }

            $occurredOn = new \DateTimeImmutable();

            // 1. Create the Domain Event
            $event = new BookingWizardCompleted(
                bookingId: $aggregateId->toRfc4122(),
                pax: $command->pax,
                budget: $command->budget,
                clientName: $command->clientName,
                clientEmail: $command->clientEmail,
                occurredOn: $occurredOn
            );

            // 2. Persist to Event Store (Source of Truth)
            $storedEvent = new StoredEvent(
                aggregateId: $aggregateId,
                eventType: BookingWizardCompleted::class,
                payload: [
                    'bookingId' => $aggregateId->toRfc4122(),
                    'pax' => $command->pax,
                    'budget' => $command->budget,
                    'clientName' => $command->clientName,
                    'clientEmail' => $command->clientEmail,
                    'occurredOn' => $occurredOn->format(\DateTimeInterface::ATOM)
                ]
            );

            $this->entityManager->persist($storedEvent);
            $this->entityManager->flush();

            // --- AUTOMATIC SNAPSHOT LOGIC ---
            $eventCount = (int)$this->readEntityManager->fetchOne('SELECT COUNT(*) FROM event_store')['count'];
            if ($eventCount > 0 && $eventCount % $this->snapshotThreshold === 0) {
                // In a real app, we capture the ACTUAL state of the aggregate.
                // For this demo, we snapshot the projection counts as "System State".
                $userCount = (int)$this->readEntityManager->fetchOne('SELECT COUNT(*) FROM users')['count'];
                $bookingCount = (int)$this->readEntityManager->fetchOne('SELECT COUNT(*) FROM bookings')['count'];

                $snapshot = new Snapshot(
                    Uuid::v7(), // System aggregate ID for demo
                    $eventCount,
                    ['users' => $userCount, 'bookings' => $bookingCount, 'auto' => true]
                );
                $this->entityManager->persist($snapshot);
                $this->entityManager->flush();
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
