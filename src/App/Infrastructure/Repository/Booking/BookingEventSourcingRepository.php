<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Booking;

use App\Domain\Model\Booking;
use App\Domain\Repository\BookingEventStoreRepositoryInterface;
use App\Infrastructure\Repository\Shared\EventSourcingRepository;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @extends EventSourcingRepository<Booking>
 */
final readonly class BookingEventSourcingRepository extends EventSourcingRepository implements BookingEventStoreRepositoryInterface
{
    public function __construct(
        MongoStore $mongoStore,
        SerializerInterface $serializer,
        int $snapshotThreshold,
    ) {
        parent::__construct(Booking::class, $mongoStore, $serializer, $snapshotThreshold);
    }
}
