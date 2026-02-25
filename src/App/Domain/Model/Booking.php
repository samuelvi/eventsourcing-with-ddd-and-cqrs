<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Event\BookingWizardCompleted;
use App\Domain\Shared\TypeAssert;
use App\Domain\ValueObject\Country;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\NonNegativeAmount;
use App\Domain\ValueObject\PersonName;
use App\Domain\ValueObject\PositiveInt;
use App\Domain\ValueObject\UuidString;
use Symfony\Component\Uid\Uuid;

final class Booking extends AggregateRoot
{
    private UuidString $userId;
    private PositiveInt $pax;
    private NonNegativeAmount $budget;
    private PersonName $clientName;
    private Email $clientEmail;
    private Country $country;

    public static function submit(
        Uuid $id,
        UuidString $userId,
        PositiveInt $pax,
        NonNegativeAmount $budget,
        PersonName $clientName,
        Email $clientEmail,
        Country $country
    ): self {
        $booking = new self($id);
        $booking->recordThat(BookingWizardCompleted::occur(
            bookingId: $id->toRfc4122(),
            userId: $userId->toString(),
            pax: $pax->toInt(),
            budget: $budget->toFloat(),
            clientName: $clientName->toString(),
            clientEmail: $clientEmail->toString(),
            country: $country->toString(),
            occurredOn: new \DateTimeImmutable(),
        ));

        return $booking;
    }

    protected function apply(object $event): void
    {
        if ($event instanceof BookingWizardCompleted) {
            $this->userId = UuidString::fromString($event->userId);
            $this->pax = PositiveInt::fromInt($event->pax);
            $this->budget = NonNegativeAmount::fromFloat($event->budget);
            $this->clientName = PersonName::fromString($event->clientName);
            $this->clientEmail = Email::fromString($event->clientEmail);
            $this->country = Country::fromString($event->country);
        }
    }

    public function getSnapshotState(): array
    {
        return [
            'userId' => $this->userId->toString(),
            'pax' => $this->pax->toInt(),
            'budget' => $this->budget->toFloat(),
            'clientName' => $this->clientName->toString(),
            'clientEmail' => $this->clientEmail->toString(),
            'country' => $this->country->toString(),
        ];
    }

    protected function applySnapshot(array $state): void
    {
        $this->userId = UuidString::fromString(TypeAssert::string($state['userId'] ?? null));
        $this->pax = PositiveInt::fromInt(TypeAssert::int($state['pax'] ?? null));
        $this->budget = NonNegativeAmount::fromFloat(TypeAssert::float($state['budget'] ?? null));
        $this->clientName = PersonName::fromString(TypeAssert::string($state['clientName'] ?? null));
        $this->clientEmail = Email::fromString(TypeAssert::string($state['clientEmail'] ?? null));
        $this->country = Country::fromString(TypeAssert::string($state['country'] ?? null));
    }
}
