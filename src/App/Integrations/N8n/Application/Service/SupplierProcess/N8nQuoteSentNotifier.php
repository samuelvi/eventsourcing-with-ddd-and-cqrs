<?php

declare(strict_types=1);

namespace App\Integrations\N8n\Application\Service\SupplierProcess;

use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class N8nQuoteSentNotifier
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ?LoggerInterface $n8nLogger = null,
    ) {}

    public function notify(
        Uuid $quoteId,
        Uuid $bookingId,
        Uuid $supplierId,
        Uuid $productId,
        float $price,
        string $correlationId,
        ?string $callbackUrl = null,
    ): void {
        $webhookUrl = $callbackUrl ?: getenv('N8N_WEBHOOK_QUOTE_SENT_URL');
        if ($webhookUrl === false || $webhookUrl === '') {
            $this->n8nLogger?->warning('N8n quote-sent webhook url is not configured');

            return;
        }

        try {
            $response = $this->httpClient->request('POST', $webhookUrl, [
                'json' => [
                    'event' => 'quote_sent_by_supplier',
                    'quoteId' => $quoteId->toRfc4122(),
                    'bookingId' => $bookingId->toRfc4122(),
                    'supplierId' => $supplierId->toRfc4122(),
                    'productId' => $productId->toRfc4122(),
                    'price' => $price,
                    'correlationId' => $correlationId,
                    'occurredOn' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                ],
                'timeout' => 10,
            ]);

            $this->n8nLogger?->info('N8n quote-sent notification sent', [
                'quoteId' => $quoteId->toRfc4122(),
                'bookingId' => $bookingId->toRfc4122(),
                'correlationId' => $correlationId,
                'status' => $response->getStatusCode(),
            ]);
        } catch (Throwable $e) {
            $this->n8nLogger?->error('N8n quote-sent notification failed', [
                'quoteId' => $quoteId->toRfc4122(),
                'bookingId' => $bookingId->toRfc4122(),
                'correlationId' => $correlationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
