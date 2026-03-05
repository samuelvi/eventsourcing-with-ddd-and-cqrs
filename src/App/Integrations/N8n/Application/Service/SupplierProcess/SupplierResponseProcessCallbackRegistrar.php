<?php

declare(strict_types=1);

namespace App\Integrations\N8n\Application\Service\SupplierProcess;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class SupplierResponseProcessCallbackRegistrar
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ?LoggerInterface $n8nLogger = null,
    ) {}

    public function notify(string $bookingId, string $correlationId): void
    {
        $webhookUrl = getenv('N8N_WEBHOOK_SUPPLIER_RESPONSE_PROCESS_URL');
        if ($webhookUrl === false || $webhookUrl === '') {
            $webhookUrl = getenv('N8N_WEBHOOK_START_QUOTE_WATCHDOG_URL');
        }

        if ($webhookUrl === false || $webhookUrl === '') {
            $this->n8nLogger?->warning('N8n supplier-response-process webhook url is not configured');

            return;
        }

        try {
            $response = $this->httpClient->request('POST', $webhookUrl, [
                'json' => [
                    'event' => 'supplier_response_process_started',
                    'bookingId' => $bookingId,
                    'correlationId' => $correlationId,
                    'occurredOn' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                ],
                'timeout' => 10,
            ]);

            $this->n8nLogger?->info('N8n supplier-response-process notification sent', [
                'bookingId' => $bookingId,
                'correlationId' => $correlationId,
                'status' => $response->getStatusCode(),
            ]);
        } catch (Throwable $e) {
            $this->n8nLogger?->error('N8n supplier-response-process notification failed', [
                'bookingId' => $bookingId,
                'correlationId' => $correlationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
