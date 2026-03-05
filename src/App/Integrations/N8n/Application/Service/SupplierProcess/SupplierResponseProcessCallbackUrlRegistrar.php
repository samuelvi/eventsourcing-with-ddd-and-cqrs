<?php

declare(strict_types=1);

namespace App\Integrations\N8n\Application\Service\SupplierProcess;

use App\Domain\Derivation\Event\QuoteCreated;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Psr\Log\LoggerInterface;

final readonly class SupplierResponseProcessCallbackUrlRegistrar
{
    public function __construct(
        private MongoStore $mongoStore,
        private ReadEntityManager $readEntityManager,
        private ?LoggerInterface $n8nLogger = null,
    ) {}

    public function register(string $bookingId, string $correlationId, string $callbackUrl): int
    {
        $quoteIds = [];

        foreach ($this->mongoStore->findEventsByBookingId($bookingId) as $event) {
            if ($event->eventType !== QuoteCreated::class) {
                continue;
            }

            $eventCorrelationId = $event->payload['correlationId'] ?? null;
            $quoteId = $event->payload['quoteId'] ?? null;

            if (!is_string($eventCorrelationId) || $eventCorrelationId !== $correlationId) {
                continue;
            }

            if (!is_string($quoteId) || $quoteId === '') {
                continue;
            }

            $quoteIds[$quoteId] = true;
        }

        if ($quoteIds === []) {
            $this->n8nLogger?->warning('No quotes found for callback registration', [
                'bookingId' => $bookingId,
                'correlationId' => $correlationId,
            ]);

            return 0;
        }

        $params = [
            'callbackUrl' => $callbackUrl,
            'bookingId' => $bookingId,
        ];

        $placeholders = [];
        $index = 0;
        foreach (array_keys($quoteIds) as $quoteId) {
            $paramName = 'quoteId' . $index;
            $params[$paramName] = $quoteId;
            $placeholders[] = ':' . $paramName;
            $index++;
        }

        $sql = sprintf(
            'UPDATE quotes SET n8n_callback_url = :callbackUrl WHERE booking_id = :bookingId AND id IN (%s)',
            implode(', ', $placeholders)
        );

        $updated = $this->readEntityManager->execute($sql, $params);

        $this->n8nLogger?->info('Registered supplier response callback url', [
            'bookingId' => $bookingId,
            'correlationId' => $correlationId,
            'updatedQuotes' => $updated,
        ]);

        return $updated;
    }
}
