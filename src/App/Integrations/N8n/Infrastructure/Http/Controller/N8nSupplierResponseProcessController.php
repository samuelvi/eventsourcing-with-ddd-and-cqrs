<?php

declare(strict_types=1);

namespace App\Integrations\N8n\Infrastructure\Http\Controller;

use App\Integrations\N8n\Application\Dto\N8nSupplierResponseProcessDto;
use App\Integrations\N8n\Application\Service\SupplierProcess\SupplierResponseProcessCallbackUrlRegistrar;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class N8nSupplierResponseProcessController
{
    public function __construct(
        private SupplierResponseProcessCallbackUrlRegistrar $callbackUrlRegistrar,
    ) {}

    #[Route('/api/integrations/n8n/supplier-response-process', name: 'api_n8n_supplier_response_process', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] N8nSupplierResponseProcessDto $payload): Response
    {
        $updatedQuotes = $this->callbackUrlRegistrar->register(
            bookingId: $payload->bookingId,
            correlationId: $payload->correlationId,
            callbackUrl: $payload->callbackUrl,
        );

        return new JsonResponse([
            'status' => 'accepted',
            'bookingId' => $payload->bookingId,
            'correlationId' => $payload->correlationId,
            'event' => $payload->event,
            'callbackUrl' => $payload->callbackUrl,
            'supplierResponded' => $payload->supplierResponded,
            'elapsedMinutes' => $payload->elapsedMinutes,
            'updatedQuotes' => $updatedQuotes,
        ], Response::HTTP_ACCEPTED);
    }
}
