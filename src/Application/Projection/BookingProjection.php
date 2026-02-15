<?php

declare(strict_types=1);

namespace App\Application\Projection;

use App\Domain\Event\BookingWizardCompleted;
use App\Domain\Model\BookingEntity;
use App\Domain\Model\UserEntity;
use App\Infrastructure\EventSourcing\ProjectionCheckpoint;
use App\Domain\Repository\BookingWriteRepositoryInterface;
use App\Domain\Repository\BookingReadRepositoryInterface;
use App\Domain\Repository\UserWriteRepositoryInterface;
use App\Domain\Repository\UserReadRepositoryInterface;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Lock\LockFactory;

#[AsMessageHandler]
final readonly class BookingProjection
{
    public function __construct(
        private BookingWriteRepositoryInterface $bookingWriteRepository,
        private BookingReadRepositoryInterface $bookingReadRepository,
        private UserWriteRepositoryInterface $userWriteRepository,
        private UserReadRepositoryInterface $userReadRepository,
        private MongoStore $mongoStore,
        private CacheInterface $cache,
        private LockFactory $lockFactory,
    ) {}

    public function __invoke(BookingWizardCompleted $event): void
    {
        // DEMO MODE: Check if booking projections are enabled
        $enabled = $this->cache->get('demo_booking_projections_enabled', fn() => true);
        if (!$enabled) {
            return;
        }

        // 1. Get User (FK requirement)
        $user = $this->userWriteRepository->find($event->userId);
        
        if (!$user) {
            error_log('BookingProjection: user not found ' . $event->userId);
            // In a real ES system, we might retry or fail. 
            // For the demo, if UserProjection is OFFLINE, this will (and should) fail 
            // due to Foreign Key constraints if we try to insert.
            // Or we can just return to show the inconsistency.
            return;
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
}
