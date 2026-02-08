<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\Command\SubmitBookingWizardCommand;
use App\Application\Dto\BookingWizardDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class BookingWizardController extends AbstractController
{
    #[Route('/api/booking-wizard', name: 'api_booking_wizard_submit', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload] BookingWizardDto $dto,
        MessageBusInterface $commandBus
    ): Response {
        $commandBus->dispatch(new SubmitBookingWizardCommand(
            id: $dto->bookingId,
            pax: $dto->pax,
            budget: $dto->budget,
            clientName: $dto->clientName,
            clientEmail: $dto->clientEmail
        ));

        return new JsonResponse(null, Response::HTTP_ACCEPTED);
    }
}
