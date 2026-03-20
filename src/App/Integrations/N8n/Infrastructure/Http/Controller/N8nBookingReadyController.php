<?php

declare(strict_types=1);

namespace App\Integrations\N8n\Infrastructure\Http\Controller;

use App\Application\Command\Quotes\GenerateQuotesCommand;
use App\Application\Service\DerivationRunContextFactory;
use App\Application\Service\DerivationRunTracker;
use App\Integrations\N8n\Application\Dto\N8nBookingReadyDto;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Routing\Attribute\Route;

final readonly class N8nBookingReadyController
{
    public function __construct(
        private DerivationRunContextFactory $derivationRunContextFactory,
        private DerivationRunTracker $derivationRunTracker,
    ) {}

    #[Route('/api/integrations/n8n/booking-ready', name: 'api_n8n_booking_ready', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] N8nBookingReadyDto $payload, MessageBusInterface $messageBus,
    ): Response
    {
        $derivationContext = $this->derivationRunContextFactory->create(
            bookingId: $payload->bookingId,
            derivationRunId: $payload->derivationRunId,
            correlationId: $payload->correlationId,
        );
        $this->derivationRunTracker->open($derivationContext);

        $messageBus->dispatch(new GenerateQuotesCommand(
            bookingId: $derivationContext->bookingId,
            derivationRunId: $derivationContext->derivationRunId,
            correlationId: $derivationContext->correlationId,
            supplierIds: $payload->supplierIds,
            productIds: $payload->productIds,
        ), [new TransportNamesStamp(['derivations_events'])]);

        return new JsonResponse([
            'status' => 'accepted',
            'bookingId' => $derivationContext->bookingId,
            'derivationRunId' => $derivationContext->derivationRunId,
            'event' => $payload->event,
            'correlationId' => $derivationContext->correlationId,
            'supplierIds' => $payload->supplierIds,
            'productIds' => $payload->productIds,
        ], Response::HTTP_ACCEPTED);
    }
}
