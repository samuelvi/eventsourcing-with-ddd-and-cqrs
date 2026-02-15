<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\SubmitBookingWizardCommand;
use App\Domain\Exception\ConcurrencyException;
use App\Domain\Model\Booking;
use App\Domain\Repository\BookingEventStoreRepositoryInterface;
use App\Domain\Shared\Constants;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class SubmitBookingWizardHandler
{
    public function __construct(
        private BookingEventStoreRepositoryInterface $bookingRepository,
        private MessageBusInterface $eventBus,
    ) {}

    public function __invoke(SubmitBookingWizardCommand $command): void
    {
        $aggregateId = Uuid::fromString($command->id);

        // Resolve User ID deterministically (No DB call!)
        $userId = Uuid::v5(Uuid::fromString(Constants::USER_NAMESPACE), strtolower(trim($command->clientEmail)));

        // 1. Create the Aggregate
        $booking = Booking::submit(
            id: $aggregateId,
            userId: $userId->toRfc4122(),
            pax: $command->pax,
            budget: $command->budget,
            clientName: $command->clientName,
            clientEmail: $command->clientEmail
        );

        $events = $booking->getRecordedEvents();

        try {
            // 2. Persist Aggregate (saves to Mongo)
            // If another process created it, MongoDB index will trigger ConcurrencyException
            $this->bookingRepository->save($booking);
        } catch (ConcurrencyException) {
            // Silently fail for idempotency
            return;
        }

        // 3. Dispatch events
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
