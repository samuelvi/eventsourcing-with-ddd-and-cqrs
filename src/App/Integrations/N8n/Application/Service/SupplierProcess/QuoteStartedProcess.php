<?php

declare(strict_types=1);

namespace App\Integrations\N8n\Application\Service\SupplierProcess;

use App\Application\Quotes\Derivation\Run\DerivationEventPublisherHandler;
use App\Application\Service\DerivationRunContext;
use App\Domain\Derivation\Event\StartQuoteProcess;
use App\Domain\Repository\QuoteWriteRepositoryInterface;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class QuoteStartedProcess
{
    public function __construct(
        private HttpClientInterface           $httpClient,
        private DerivationEventPublisherHandler $eventPublisher,
        private QuoteWriteRepositoryInterface $quoteWriteRepository,
        private ?LoggerInterface              $n8nLogger = null,
    )
    {
    }

    /**
     * @param array<int, string> $quoteIds
     */
    public function notify(DerivationRunContext $context, array $quoteIds): void
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
                    'bookingId' => $context->bookingId,
                    'derivationRunId' => $context->derivationRunId,
                    'correlationId' => $context->correlationId,
                    'quoteIds' => $quoteIds,
                    'occurredOn' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                ],
                'timeout' => 10,
            ]);

            $this->eventPublisher->publishStartQuoteProcess(new StartQuoteProcess(
                derivationRunId: $context->derivationRunId,
                correlationId: $context->correlationId,
                bookingId: $context->bookingId,
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

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                $this->n8nLogger?->warning('Error to send quote webhook', [
                    'response' => $response->getStatusCode(),
                    'webhookUrl' => $webhookUrl,
                    'derivationRunId' => $context->derivationRunId,
                    'correlationId' => $context->correlationId,
                    'errorMessage' => $response->getContent(),
                ]);
            }

            if ($callbackUrl === null) {
                $this->n8nLogger?->warning('N8n start-quote response without callback url', [
                    'bookingId' => $context->bookingId,
                    'derivationRunId' => $context->derivationRunId,
                    'correlationId' => $context->correlationId,
                    'responsePayload' => $responsePayload,
                ]);
            }

            $this->n8nLogger?->info('N8n supplier-response-process notification sent', [
                'bookingId' => $context->bookingId,
                'derivationRunId' => $context->derivationRunId,
                'correlationId' => $context->correlationId,
                'status' => $response->getStatusCode(),
                'callbackUrl' => $callbackUrl,
                'updatedQuotes' => $updatedQuotes,
            ]);
        } catch (Throwable $e) {
            $this->n8nLogger?->error('N8n supplier-response-process notification failed', [
                'bookingId' => $context->bookingId,
                'derivationRunId' => $context->derivationRunId,
                'correlationId' => $context->correlationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

}
