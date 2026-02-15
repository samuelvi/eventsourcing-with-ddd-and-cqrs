<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Event\BookingWizardCompleted;
use Symfony\Component\Uid\Uuid;

final class Booking extends AggregateRoot
{
    private string $userId;
    private int $pax;
    private float $budget;
    private string $clientName;
    private string $clientEmail;

    public static function submit(
        Uuid $id,
        string $userId,
        int $pax,
        float $budget,
        string $clientName,
        string $clientEmail
    ): self {
        $booking = new self($id);
        $booking->recordThat(BookingWizardCompleted::occur(
            bookingId: $id->toRfc4122(),
            userId: $userId,
            pax: $pax,
            budget: $budget,
            clientName: $clientName,
            clientEmail: $clientEmail,
            occurredOn: new \DateTimeImmutable()
        ));

        return $booking;
    }

    protected function apply(object $event): void
    {
        if ($event instanceof BookingWizardCompleted) {
            $this->userId = $event->userId;
            $this->pax = $event->pax;
            $this->budget = $event->budget;
            $this->clientName = $event->clientName;
            $this->clientEmail = $event->clientEmail;
        }
    }

    public function getSnapshotState(): array
    {
        return [
            'userId' => $this->userId,
            'pax' => $this->pax,
            'budget' => $this->budget,
            'clientName' => $this->clientName,
            'clientEmail' => $this->clientEmail,
        ];
    }

    protected function applySnapshot(array $state): void
    {
        $this->userId = $state['userId'];
        $this->pax = $state['pax'];
        $this->budget = $state['budget'];
        $this->clientName = $state['clientName'];
        $this->clientEmail = $state['clientEmail'];
    }
}
