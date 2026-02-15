<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Event\QuoteStatusChanged;
use App\Domain\Model\QuoteEntity;
use App\Infrastructure\EventSourcing\StoredEvent;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use App\Domain\Repository\QuoteWriteRepositoryInterface;
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
    ) {}

    /**
     * @param QuoteEntity $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): QuoteEntity
    {
        $occurredOn = new \DateTimeImmutable();

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
                'occurredOn' => $occurredOn->format(\DateTimeInterface::ATOM)
            ],
            occurredOn: $occurredOn
        );

        $this->mongoStore->saveEvent($storedEvent);

        // 3. Update Read Model (Sync for simplicity in this action)
        $this->writeRepository->save($data);

        // 4. Dispatch for other potential listeners
        $this->eventBus->dispatch($event);

        return $data;
    }
}
