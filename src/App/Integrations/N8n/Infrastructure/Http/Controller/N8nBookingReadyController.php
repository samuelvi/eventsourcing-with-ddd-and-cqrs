<?php

declare(strict_types=1);

namespace App\Integrations\N8n\Infrastructure\Http\Controller;

use App\Application\Command\Quotes\GenerateQuotesCommand;
use App\Integrations\N8n\Application\Dto\N8nBookingReadyDto;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Routing\Attribute\Route;

final readonly class N8nBookingReadyController
{
    #[Route('/api/integrations/n8n/booking-ready', name: 'api_n8n_booking_ready', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] N8nBookingReadyDto $payload, MessageBusInterface $messageBus,
    ): Response
    {
        $messageBus->dispatch(new GenerateQuotesCommand($payload->bookingId, $payload->correlationId), [new TransportNamesStamp(['derivations_events'])]);

        return new JsonResponse([
            'status' => 'accepted',
            'bookingId' => $payload->bookingId,
            'event' => $payload->event,
            'correlationId' => $payload->correlationId,
        ], Response::HTTP_ACCEPTED);
    }
}
