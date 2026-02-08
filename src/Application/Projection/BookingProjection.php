<?php

declare(strict_types=1);

namespace App\Application\Projection;

use App\Domain\Event\BookingWizardCompleted;
use App\Domain\Model\BookingEntity;
use App\Domain\Model\ProjectionCheckpoint;
use App\Domain\Repository\BookingWriteRepositoryInterface;
use App\Domain\Repository\BookingReadRepositoryInterface;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;

#[AsMessageHandler]
final readonly class BookingProjection
{
    public function __construct(
        private BookingWriteRepositoryInterface $bookingWriteRepository,
        private BookingReadRepositoryInterface $bookingReadRepository,
        private MongoStore $mongoStore,
        private CacheInterface $cache,
    ) {}

    public function __invoke(BookingWizardCompleted $event): void
    {
        // DEMO MODE: Check if booking projections are enabled
        $enabled = $this->cache->get('demo_booking_projections_enabled', fn() => true);
        if (!$enabled) {
            return;
        }

        // Idempotency check: Does this booking exist?
        if (!$this->bookingReadRepository->exists($event->bookingId)) {
            $data = [
                'pax' => $event->pax,
                'budget' => $event->budget,
                'clientName' => $event->clientName,
                'clientEmail' => $event->clientEmail,
            ];

            $booking = new BookingEntity(
                Uuid::fromString($event->bookingId),
                $data,
                $event->occurredOn
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
