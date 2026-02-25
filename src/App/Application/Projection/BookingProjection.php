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
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\NonNegativeAmount;
use App\Domain\ValueObject\PersonName;
use App\Domain\ValueObject\PositiveInt;
use App\Domain\ValueObject\Country;
use App\Domain\ValueObject\UuidString;
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
        $bookingId = UuidString::fromString($event->bookingId);
        $userId = UuidString::fromString($event->userId);
        $pax = PositiveInt::fromInt($event->pax);
        $budget = NonNegativeAmount::fromFloat($event->budget);
        $clientName = PersonName::fromString($event->clientName);
        $clientEmail = Email::fromString($event->clientEmail);
        $country = Country::fromString($event->country);

        // DEMO MODE: projection is active only if both master and booking switches are active.
        if (!$this->isProjectionEnabled()) {
            return;
        }

        // 1. Get User (FK requirement)
        $user = $this->userWriteRepository->find($userId->toString());
        
        if (!$user) {
            // This is a recoverable error. The UserRegistered event might be processed after this one.
            // Throwing this specific exception will tell Messenger to retry with a delay.
            throw new RecoverableMessageException(sprintf('User %s not found for booking projection. Message will be retried.', $userId->toString()));
        }

        // 2. Idempotency check: Does this booking exist?
        if (!$this->bookingReadRepository->exists($bookingId->toString())) {
            $data = [
                'pax' => $pax->toInt(),
                'budget' => $budget->toFloat(),
                'clientName' => $clientName->toString(),
                'clientEmail' => $clientEmail->toString(),
                'country' => $country->toString(),
            ];

            $booking = BookingEntity::create(
                id: Uuid::fromString($bookingId->toString()),
                user: $user,
                data: $data,
                createdAt: $event->occurredOn
            );
            $booking->country = $country->toString();

            $this->bookingWriteRepository->save($booking);
        }

        // Update Checkpoint in Mongo
        $checkpoint = $this->mongoStore->findCheckpoint('booking_projection');
        if (!$checkpoint) {
            $checkpoint = ProjectionCheckpoint::create('booking_projection');
        }
        $checkpoint->update(Uuid::fromString($bookingId->toString()));
        $this->mongoStore->saveCheckpoint($checkpoint);
    }

    private function isProjectionEnabled(): bool
    {
        $master = $this->cache->get(self::CACHE_KEY_MASTER, fn() => true);
        $booking = $this->cache->get(self::CACHE_KEY_BOOKING, fn() => true);

        return $master && $booking;
    }
}
