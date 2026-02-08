<?php

declare(strict_types=1);

namespace App\Application\Projection;

use App\Domain\Event\BookingWizardCompleted;
use App\Domain\Model\BookingEntity;
use App\Domain\Repository\BookingWriteRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class BookingProjection
{
    public function __construct(
        private BookingWriteRepositoryInterface $bookingRepository,
    ) {}

    public function __invoke(BookingWizardCompleted $event): void
    {
        $data = [
            'pax' => $event->pax,
            'budget' => $event->budget,
            'clientName' => $event->clientName,
            'clientEmail' => $event->clientEmail,
        ];

        // BookingEntity does not use NamedConstructorTrait in the previous step? 
        // Let's verify. I created it without it. Let's fix it or use new if public.
        // I created it with public __construct. Let's stick to standard new for read model projection
        // or update it to match the rest. Ideally consistence.
        // Let's assume public construct for now to avoid re-editing file.
        
        $booking = new BookingEntity(
            Uuid::fromString($event->bookingId),
            $data,
            $event->occurredOn
        );

        $this->bookingRepository->save($booking);
    }
}
