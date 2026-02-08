<?php

declare(strict_types=1);

namespace App\Application\Projection;

use App\Domain\Event\BookingWizardCompleted;
use App\Domain\Model\BookingEntity;
use App\Domain\Model\ProjectionCheckpoint;
use App\Domain\Repository\BookingWriteRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\WriteEntityManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;

#[AsMessageHandler]
final readonly class BookingProjection
{
    public function __construct(
        private BookingWriteRepositoryInterface $bookingRepository,
        private WriteEntityManager $writeEntityManager,
        private CacheInterface $cache,
    ) {}

    public function __invoke(BookingWizardCompleted $event): void
    {
        // DEMO MODE: Check if booking projections are enabled
        $enabled = $this->cache->get('demo_booking_projections_enabled', fn() => true);
        if (!$enabled) {
            return;
        }

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

        $this->bookingRepository->save($booking);

        // Update Checkpoint
        $checkpoint = $this->writeEntityManager->find(ProjectionCheckpoint::class, 'booking_projection');
        if (!$checkpoint) {
            $checkpoint = new ProjectionCheckpoint('booking_projection');
            $this->writeEntityManager->persist($checkpoint);
        }
        $checkpoint->update(Uuid::fromString($event->bookingId));
        $this->writeEntityManager->flush();
    }
}
