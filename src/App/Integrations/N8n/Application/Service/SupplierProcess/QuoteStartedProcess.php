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
            $this->n8nLogger?->warning('N8n start-quote webhook url is not configured');
            return;
        }

        $this->eventPublisher->publishStartQuoteProcess(new StartQuoteProcess(
            derivationRunId: $context->derivationRunId,
            correlationId: $context->correlationId,
            bookingId: $context->bookingId,
            occurredOn: new DateTimeImmutable(),
        ));

        foreach ($quoteIds as $quoteId) {
            try {
                $response = $this->httpClient->request('POST', $webhookUrl, [
                    'json' => [
                        'event' => StartQuoteProcess::class,
                        'bookingId' => $context->bookingId,
                        'derivationRunId' => $context->derivationRunId,
                        'correlationId' => $context->correlationId,
                        'quoteId' => $quoteId,      // singular, solo esta quote
                        'quoteIds' => $quoteIds,    // el array completo del round, para contexto
                        'occurredOn' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                    ],
                    'timeout' => 10,
                ]);

                $responsePayload = json_decode($response->getContent(false), true);
                $callbackUrl = is_array($responsePayload) && is_string($responsePayload['callbackUrl'] ?? null)
                    ? $responsePayload['callbackUrl']
                    : null;

                if ($callbackUrl !== null && $callbackUrl !== '') {
                    $this->quoteWriteRepository->callbackUpdate($quoteId, $callbackUrl);
                } else {
                    $this->n8nLogger?->warning('N8n start-quote response without callbackUrl', [
                        'quoteId' => $quoteId,
                        'correlationId' => $context->correlationId,
                    ]);
                }

                $this->n8nLogger?->info('N8n start-quote notification sent', [
                    'quoteId' => $quoteId,
                    'bookingId' => $context->bookingId,
                    'correlationId' => $context->correlationId,
                    'callbackUrl' => $callbackUrl,
                ]);

            } catch (Throwable $e) {
                $this->n8nLogger?->error('N8n start-quote notification failed', [
                    'quoteId' => $quoteId,
                    'bookingId' => $context->bookingId,
                    'correlationId' => $context->correlationId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

}
