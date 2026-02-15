<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Booking;

/**
 * @extends EventStoreRepositoryInterface<Booking>
 */
interface BookingEventStoreRepositoryInterface extends EventStoreRepositoryInterface
{
}
