<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Model\Booking;
use App\Domain\Repository\BookingEventStoreRepositoryInterface;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @extends EventSourcingRepository<Booking>
 */
final readonly class BookingEventSourcingRepository extends EventSourcingRepository implements BookingEventStoreRepositoryInterface
{
    public function __construct(
        MongoStore $mongoStore,
        SerializerInterface $serializer,
        MessageBusInterface $eventBus,
    ) {
        parent::__construct(Booking::class, $mongoStore, $serializer, $eventBus);
    }
}
