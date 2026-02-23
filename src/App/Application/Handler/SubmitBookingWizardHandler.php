<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\SubmitBookingWizardCommand;
use App\Domain\Exception\ConcurrencyException;
use App\Domain\Model\Booking;
use App\Domain\Model\User;
use App\Domain\Repository\BookingEventStoreRepositoryInterface;
use App\Domain\Repository\UserEventStoreRepositoryInterface;
use App\Domain\Shared\Constants;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\NonNegativeAmount;
use App\Domain\ValueObject\PersonName;
use App\Domain\ValueObject\PositiveInt;
use App\Domain\ValueObject\UuidString;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class SubmitBookingWizardHandler
{
    public function __construct(
        private BookingEventStoreRepositoryInterface $bookingRepository,
        private UserEventStoreRepositoryInterface $userRepository,
        private MessageBusInterface $eventBus,
    ) {}

    public function __invoke(SubmitBookingWizardCommand $command): void
    {
        $bookingId = Uuid::fromString($command->id);
        $clientEmail = Email::fromString($command->clientEmail);
        $clientName = PersonName::fromString($command->clientName);

        // Resolve User ID deterministically (No DB call!)
        $userId = Uuid::v5(Uuid::fromString(Constants::USER_NAMESPACE), $clientEmail->toString());

        // 1. Ensure User Aggregate exists in Event Store (Write Side)
        // If it doesn't exist, we must register it so it can be updated later
        if (!$this->userRepository->get($userId)) {
            $user = User::register(
                $userId,
                $clientName,
                $clientEmail
            );

            try {
                $this->userRepository->save($user);
                foreach ($user->getRecordedEvents() as $event) {
                    $this->eventBus->dispatch($event);
                }
            } catch (ConcurrencyException) {
                // Silently continue if another process registered the user meanwhile
            }
        }

        // 2. Create the Booking Aggregate
        $booking = Booking::submit(
            id: $bookingId,
            userId: UuidString::fromString($userId->toRfc4122()),
            pax: PositiveInt::fromInt($command->pax),
            budget: NonNegativeAmount::fromFloat($command->budget),
            clientName: $clientName,
            clientEmail: $clientEmail
        );

        $events = $booking->getRecordedEvents();

        try {
            // 3. Persist Booking Aggregate (saves to Mongo)
            $this->bookingRepository->save($booking);
        } catch (ConcurrencyException) {
            // Silently fail for idempotency
            return;
        }

        // 4. Dispatch booking events
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
