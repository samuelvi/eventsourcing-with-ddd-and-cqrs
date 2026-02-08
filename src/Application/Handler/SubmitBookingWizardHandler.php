<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\SubmitBookingWizardCommand;
use App\Domain\Event\BookingWizardCompleted;
use App\Domain\Model\StoredEvent;
use App\Infrastructure\Persistence\Doctrine\WriteEntityManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Cache\CacheInterface;

#[AsMessageHandler]
final readonly class SubmitBookingWizardHandler
{
    public function __construct(
        private WriteEntityManager $entityManager,
        private MessageBusInterface $eventBus,
        private LockFactory $lockFactory,
        private CacheInterface $cache,
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
                // If it exists, we could either throw a Conflict exception 
                // or just return if we want it to be silent (idempotent).
                // For a "pure" approach and to prevent attacks, we can just stop.
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
            // ALWAYS save the event, this is the core of Event Sourcing
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

            // 3. Dispatch to Async Bus (for Projections)
            // DEMO MODE: Skip dispatch if projections are disabled to simulate failure
            $projectionsEnabled = $this->cache->get('demo_projections_enabled', fn() => true);
            if ($projectionsEnabled) {
                $this->eventBus->dispatch($event);
            }
        } finally {
            $lock->release();
        }
    }
}
