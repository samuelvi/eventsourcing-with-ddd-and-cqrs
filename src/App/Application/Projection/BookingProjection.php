<?php

declare(strict_types=1);

namespace App\Application\Projection;

use App\Application\Exception\RecoverableMessageException;
use App\Domain\Event\BookingWizardCompleted;
use App\Domain\Model\BookingEntity;
use App\Infrastructure\EventSourcing\ProjectionCheckpoint;
use App\Domain\Repository\BookingWriteRepositoryInterface;
use App\Domain\Repository\BookingReadRepositoryInterface;
use App\Domain\Repository\UserWriteRepositoryInterface;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;

#[AsMessageHandler]
final readonly class BookingProjection
{
    private const CACHE_KEY_MASTER = 'demo_projections_enabled';
    private const CACHE_KEY_BOOKING = 'demo_booking_projections_enabled';

    public function __construct(
        private BookingWriteRepositoryInterface $bookingWriteRepository,
        private BookingReadRepositoryInterface $bookingReadRepository,
        private UserWriteRepositoryInterface $userWriteRepository,
        private MongoStore $mongoStore,
        private CacheInterface $cache
    ) {}

    public function __invoke(BookingWizardCompleted $event): void
    {
        // DEMO MODE: projection is active only if both master and booking switches are active.
        if (!$this->isProjectionEnabled()) {
            return;
        }

        // 1. Get User (FK requirement)
        $user = $this->userWriteRepository->find($event->userId);
        
        if (!$user) {
            // This is a recoverable error. The UserRegistered event might be processed after this one.
            // Throwing this specific exception will tell Messenger to retry with a delay.
            throw new RecoverableMessageException(sprintf('User %s not found for booking projection. Message will be retried.', $event->userId));
        }

        // 2. Idempotency check: Does this booking exist?
        if (!$this->bookingReadRepository->exists($event->bookingId)) {
            $data = [
                'pax' => $event->pax,
                'budget' => $event->budget,
                'clientName' => $event->clientName,
                'clientEmail' => $event->clientEmail,
            ];

            $booking = BookingEntity::create(
                id: Uuid::fromString($event->bookingId),
                user: $user,
                data: $data,
                createdAt: $event->occurredOn
            );

            $this->bookingWriteRepository->save($booking);
        }

        // Update Checkpoint in Mongo
        $checkpoint = $this->mongoStore->findCheckpoint('booking_projection');
        if (!$checkpoint) {
            $checkpoint = ProjectionCheckpoint::create('booking_projection');
        }
        $checkpoint->update(Uuid::fromString($event->bookingId));
        $this->mongoStore->saveCheckpoint($checkpoint);
    }

    private function isProjectionEnabled(): bool
    {
        $master = $this->cache->get(self::CACHE_KEY_MASTER, fn() => true);
        $booking = $this->cache->get(self::CACHE_KEY_BOOKING, fn() => true);

        return $master && $booking;
    }
}
