<?php

declare(strict_types=1);

namespace App\Integrations\N8n\Application\Service\SupplierProcess;

use App\Application\Handler\Quotes\DerivationEventPublisher;
use App\Domain\Derivation\Event\StartQuoteProcess;
use App\Domain\Repository\QuoteWriteRepositoryInterface;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class QuoteStartedProcess
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private DerivationEventPublisher $eventPublisher,
        private QuoteWriteRepositoryInterface $quoteWriteRepository,
        private ?LoggerInterface $n8nLogger = null,
    )
    {
    }

    /**
     * @param array<int, string> $quoteIds
     */
    public function notify(string $bookingId, string $correlationId, array $quoteIds): void
    {
        $webhookUrl = getenv('N8N_WEBHOOK_START_QUOTE_URL');

        if ($webhookUrl === false || $webhookUrl === '') {
            $this->n8nLogger?->warning('N8n start-quote webhook url is not configured', [$webhookUrl]);
            return;
        }

        try {

            $response = $this->httpClient->request('POST', $webhookUrl, [
                'json' => [
                    'event' => StartQuoteProcess::class,
                    'bookingId' => $bookingId,
                    'correlationId' => $correlationId,
                    'occurredOn' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                ],
                'timeout' => 10,
            ]);

            $this->eventPublisher->publishStartQuoteProcess(new StartQuoteProcess(
                correlationId: $correlationId,
                bookingId: $bookingId,
                occurredOn: new DateTimeImmutable(),
            ));

            $responsePayload = json_decode($response->getContent(false), true);
            $callbackUrl = is_array($responsePayload) && is_string($responsePayload['callbackUrl'] ?? null)
                ? $responsePayload['callbackUrl']
                : null;

            $updatedQuotes = 0;
            if ($callbackUrl !== null && $callbackUrl !== '') {
                foreach ($quoteIds as $quoteId) {
                    $updatedQuotes += $this->quoteWriteRepository->callbackUpdate($quoteId, $callbackUrl);
                }
            }

            if ( $response->getStatusCode() !== 200 ) {
                $this->n8nLogger?->warning('Error to send quote webhook', [
                    'response' => $response->getStatusCode(),
                    'webhookUrl' => $webhookUrl,
                    'correlationId' => $correlationId,
                    'errorMessage' => $response->getContent(),
                ]);
            }

            $this->n8nLogger?->info('N8n supplier-response-process notification sent', [
                'bookingId' => $bookingId,
                'correlationId' => $correlationId,
                'status' => $response->getStatusCode(),
                'callbackUrl' => $callbackUrl,
                'updatedQuotes' => $updatedQuotes,
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
