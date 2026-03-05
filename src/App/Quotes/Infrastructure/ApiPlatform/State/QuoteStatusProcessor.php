<?php

declare(strict_types=1);

namespace App\Quotes\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Event\QuoteStatusChanged;
use App\Domain\Model\QuoteEntity;
use App\Domain\Repository\QuoteWriteRepositoryInterface;
use App\Infrastructure\EventSourcing\StoredEvent;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use App\Integrations\N8n\Application\Service\SupplierProcess\N8nQuoteSentNotifier;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<QuoteEntity, QuoteEntity>
 */
final readonly class QuoteStatusProcessor implements ProcessorInterface
{
    public function __construct(
        private MongoStore $mongoStore,
        private MessageBusInterface $eventBus,
        private QuoteWriteRepositoryInterface $writeRepository,
        private N8nQuoteSentNotifier $quoteSentNotifier,
    ) {}

    /**
     * @param QuoteEntity $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): QuoteEntity
    {
        $occurredOn = new \DateTimeImmutable();
        $correlationId = $this->resolveCorrelationId($data->bookingId) ?? Uuid::v7()->toRfc4122();
        $version = $this->nextVersionForAggregate($data->id);

        
        // 1. Create Domain Event
        $event = new QuoteStatusChanged(
            quoteId: $data->id->toRfc4122(),
            newStatus: $data->status,
            occurredOn: $occurredOn
        );

        // 2. Persist to Event Store
        $storedEvent = StoredEvent::commit(
            aggregateId: $data->id,
            eventType: QuoteStatusChanged::class,
            payload: [
                'quoteId' => $data->id->toRfc4122(),
                'newStatus' => $data->status,
                'correlationId' => $correlationId,
                'occurredOn' => $occurredOn->format(\DateTimeInterface::ATOM)
            ],
            version: $version,
            occurredOn: $occurredOn
        );

        $this->mongoStore->saveEvent($storedEvent);

        // 3. Update Read Model (Sync for simplicity in this action)
        $this->writeRepository->save($data);


        // 4. Notify n8n immediately when supplier sends quote
        if ($data->status === QuoteEntity::STATUS_QUOTE_SENT) {
            $this->quoteSentNotifier->notify(
                quoteId: $data->id,
                bookingId: $data->bookingId,
                supplierId: $data->supplierId,
                productId: $data->productId,
                price: $data->price,
                correlationId: $correlationId,
                callbackUrl: $data->n8nCallbackUrl,
            );
        }

        // 5. Dispatch for other potential listeners
        $this->eventBus->dispatch($event);

        return $data;
    }

    private function resolveCorrelationId(Uuid $bookingId): ?string
    {
        $events = $this->mongoStore->findAllEventsByAggregateId($bookingId);

        for ($i = count($events) - 1; $i >= 0; $i--) {
            $storedEvent = $events[$i];
            $correlationId = $storedEvent->payload['correlationId'] ?? null;
            if (is_string($correlationId) && $correlationId !== '') {
                return $correlationId;
            }
        }

        return null;
    }

    private function nextVersionForAggregate(Uuid $aggregateId): int
    {
        $events = $this->mongoStore->findAllEventsByAggregateId($aggregateId);
        if ($events === []) {
            return 1;
        }

        $lastEvent = $events[count($events) - 1];

        return $lastEvent->version + 1;
    }
}
