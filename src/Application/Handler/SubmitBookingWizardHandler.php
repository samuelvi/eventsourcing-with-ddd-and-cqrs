<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\SubmitBookingWizardCommand;

use App\Domain\Model\Booking;

use App\Domain\Repository\BookingEventStoreRepositoryInterface;

use App\Domain\Shared\Constants;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use Symfony\Component\Messenger\MessageBusInterface;

use Symfony\Component\Uid\Uuid;

use Symfony\Component\Lock\LockFactory;



#[AsMessageHandler]

final readonly class SubmitBookingWizardHandler

{

    public function __construct(

        private BookingEventStoreRepositoryInterface $bookingRepository,

        private LockFactory $lockFactory,

        private MessageBusInterface $eventBus,

    ) {}



    public function __invoke(SubmitBookingWizardCommand $command): void

    {

        $aggregateId = Uuid::fromString($command->id);

        $lock = $this->lockFactory->createLock('booking_init_' . $aggregateId->toRfc4122());



        if (!$lock->acquire(true)) {

            return;

        }



        $events = [];

        try {

            // Idempotency check: try to load the booking

            $booking = $this->bookingRepository->get($aggregateId);



            if ($booking) {

                return;

            }



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



            // 2. Persist Aggregate (saves to Mongo)

            $this->bookingRepository->save($booking);



        } finally {

            $lock->release();

        }



        // 3. Dispatch events after lock is released

        foreach ($events as $event) {

            $this->eventBus->dispatch($event);

        }

    }

}


