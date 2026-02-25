<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model;

use App\Domain\Event\BookingWizardCompleted;
use App\Domain\Model\Booking;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\NonNegativeAmount;
use App\Domain\ValueObject\PersonName;
use App\Domain\ValueObject\PositiveInt;
use App\Domain\ValueObject\Country;
use App\Domain\ValueObject\UuidString;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class BookingTest extends TestCase
{
    public function testSubmitRecordsBookingWizardCompletedEvent(): void
    {
        $bookingId = Uuid::v7();
        $userId = Uuid::v7()->toRfc4122();

        $booking = Booking::submit(
            $bookingId,
            UuidString::fromString($userId),
            PositiveInt::fromInt(2),
            NonNegativeAmount::fromFloat(1500.0),
            PersonName::fromString('Alice'),
            Email::fromString('alice@example.com'),
            Country::fromString('ES')
        );

        $events = $booking->getRecordedEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(BookingWizardCompleted::class, $events[0]);
        self::assertSame(1500.0, $events[0]->budget);
        self::assertSame(2, $events[0]->pax);
        self::assertSame('ES', $events[0]->country);
    }
}
