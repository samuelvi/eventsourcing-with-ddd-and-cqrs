<?php

declare(strict_types=1);

namespace App\Integrations\N8n\Infrastructure\Http\Controller;

use App\Application\Command\Quotes\GenerateQuotesCommand;
use App\Application\Quotes\Derivation\Run\DerivationEventPublisherHandler;
use App\Application\Service\DerivationRunContextFactory;
use App\Application\Service\DerivationRunTracker;
use App\Domain\Derivation\Event\QuoteRestartProcess;
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
        private DerivationEventPublisherHandler $derivationEventPublisherHandler,
    ) {}

    #[Route('/api/integrations/n8n/booking-ready', name: 'api_n8n_booking_ready', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] N8nBookingReadyDto $payload, MessageBusInterface $messageBus,
    ): Response
    {
        $isQuoteRestartProcess = $payload->event === N8nBookingReadyDto::EVENT_QUOTE_RESTART_PROCESS;
        $derivationContext = $isQuoteRestartProcess
            ? $this->derivationRunContextFactory->create(
                bookingId: $payload->bookingId,
                correlationId: $payload->correlationId,
            )
            : $this->derivationRunContextFactory->create(
                bookingId: $payload->bookingId,
                derivationRunId: $payload->derivationRunId,
                correlationId: $payload->correlationId,
            );
        $this->derivationRunTracker->open($derivationContext);

        if ($isQuoteRestartProcess) {
            $this->derivationEventPublisherHandler->publishQuoteRestartProcess(new QuoteRestartProcess(
                derivationRunId: $derivationContext->derivationRunId,
                correlationId: $derivationContext->correlationId,
                bookingId: $derivationContext->bookingId,
            ));
        }

        $messageBus->dispatch(new GenerateQuotesCommand(
            bookingId: $derivationContext->bookingId,
            derivationRunId: $derivationContext->derivationRunId,
            correlationId: $derivationContext->correlationId,
        ), [new TransportNamesStamp(['derivations_events'])]);

        if ($isQuoteRestartProcess) {
            return new JsonResponse([
                'bookingId' => $derivationContext->bookingId,
                'event' => $payload->event,
                'correlationId' => $derivationContext->correlationId,
            ], Response::HTTP_ACCEPTED);
        }

        return new JsonResponse([
            'status' => 'accepted',
            'bookingId' => $derivationContext->bookingId,
            'derivationRunId' => $derivationContext->derivationRunId,
            'event' => $payload->event,
            'correlationId' => $derivationContext->correlationId,
        ], Response::HTTP_ACCEPTED);
    }
}
