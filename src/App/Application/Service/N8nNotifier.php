<?php

declare(strict_types=1);

namespace App\Application\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class N8nNotifier
{

    public function __construct(
        private HttpClientInterface $httpClient,
        private ?LoggerInterface $n8nLogger = null,
    ) {

    }

    public function notifyBookingReady(Uuid $bookingId): void
    {
        $webhookUrl = getenv('N8N_WEBHOOK_BOOKING_URL');

        $correlationId = Uuid::v7()->toRfc4122();

        try {

            $response = $this->httpClient->request('POST', $webhookUrl, [
                'json' => [
                    'bookingId' => $bookingId,
                    'event' => 'booking_ready_for_derivation',
                    'correlationId' => $correlationId,
                    'occurredOn' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ],
                'timeout' => 10,
            ]);

            $this->n8nLogger?->info('N8n notification sent', [
                'bookingId' => $bookingId,
                'correlationId' => $correlationId,
                'status' => $response->getStatusCode(),
            ]);

        } catch (Throwable $e) {
            $this->n8nLogger?->error('N8n notification failed', [
                'bookingId' => $bookingId,
                'correlationId' => $correlationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
