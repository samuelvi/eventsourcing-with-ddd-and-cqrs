<?php

declare(strict_types=1);

namespace App\Application\Handler\Quotes;

use App\Domain\Derivation\Event\QuoteFlowFinsih;
use App\Domain\Exception\ConcurrencyException;
use App\Domain\Derivation\Event\QuoteCreated;
use App\Domain\Derivation\Event\QuoteLimited;
use App\Domain\Derivation\Event\QuoteLimitedByRules;
use App\Domain\Derivation\Event\QuoteNotified;
use App\Domain\Derivation\Event\StartQuoteProcess;
use App\Infrastructure\EventSourcing\StoredEvent;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Uid\Uuid;

final readonly class DerivationEventPublisher
{
    public function __construct(
        private MongoStore $mongoStore,
    ) {}

    public function publishLimited(QuoteLimited $event): void
    {
        $aggregateId = Uuid::fromString($event->bookingId);

        if ($this->alreadyPublishedByTypeAndCorrelation($aggregateId, QuoteLimited::class, $event->correlationId)) {
            return;
        }

        $this->saveEvent(
            aggregateId: $aggregateId,
            eventType: QuoteLimited::class,
            payload: [
                'bookingId' => $event->bookingId,
                'derivationRunId' => $event->derivationRunId,
                'limit' => $event->limit,
                'totalCandidates' => $event->totalCandidates,
                'selected' => $event->selected,
                'correlationId' => $event->correlationId,
            ],
            occurredOn: $event->occurredOn,
        );
    }

    public function publishFlowFinished(QuoteFlowFinsih $event): void
    {
        $aggregateId = Uuid::fromString($event->bookingId);

        if ($this->alreadyPublishedByTypeAndCorrelation($aggregateId, QuoteFlowFinsih::class, $event->correlationId)) {
            return;
        }

        $this->saveEvent(
            aggregateId: $aggregateId,
            eventType: QuoteFlowFinsih::class,
            payload: [
                'bookingId' => $event->bookingId,
                'derivationRunId' => $event->derivationRunId,
                'correlationId' => $event->correlationId,
                'lastEvent' => $event->lastEvent,
            ],
            occurredOn: $event->occurredOn,
        );
    }

    public function publishLimitedByRules(QuoteLimitedByRules $event): void
    {
        $aggregateId = Uuid::fromString($event->bookingId);

        if ($this->alreadyPublishedByTypeAndCorrelation($aggregateId, QuoteLimitedByRules::class, $event->correlationId)) {
            return;
        }

        $this->saveEvent(
            aggregateId: $aggregateId,
            eventType: QuoteLimitedByRules::class,
            payload: [
                'bookingId' => $event->bookingId,
                'derivationRunId' => $event->derivationRunId,
                'limit' => $event->limit,
                'totalAfterRules' => $event->totalAfterRules,
                'totalCandidates' => $event->totalCandidates,
                'selected' => $event->selected,
                'correlationId' => $event->correlationId,
            ],
            occurredOn: $event->occurredOn,
        );
    }

    public function publishCreated(QuoteCreated $event): void
    {
        $this->saveEvent(
            aggregateId: Uuid::fromString($event->quoteId),
            eventType: QuoteCreated::class,
            payload: [
                'quoteId' => $event->quoteId,
                'bookingId' => $event->bookingId,
                'derivationRunId' => $event->derivationRunId,
                'supplierId' => $event->supplierId,
                'productId' => $event->productId,
                'price' => $event->price,
                'correlationId' => $event->correlationId,
            ],
            occurredOn: $event->occurredOn,
        );
    }

    public function publishStartQuoteProcess(StartQuoteProcess $event): void
    {
        $aggregateId = Uuid::fromString($event->bookingId);

        if ($this->alreadyPublishedByTypeAndCorrelation($aggregateId, StartQuoteProcess::class, $event->correlationId)) {
            return;
        }

        $this->saveEvent(
            aggregateId: $aggregateId,
            eventType: StartQuoteProcess::class,
            payload: [
                'bookingId' => $event->bookingId,
                'derivationRunId' => $event->derivationRunId,
                'correlationId' => $event->correlationId,
            ],
            occurredOn: $event->occurredOn,
        );
    }


    public function publishNotified(QuoteNotified $event): void
    {
        $this->saveEvent(
            aggregateId: Uuid::fromString($event->quoteId),
            eventType: QuoteNotified::class,
            payload: [
                'quoteId' => $event->quoteId,
                'bookingId' => $event->bookingId,
                'derivationRunId' => $event->derivationRunId,
                'supplierId' => $event->supplierId,
                'notificationMethod' => $event->notificationMethod,
                'correlationId' => $event->correlationId,
            ],
            occurredOn: $event->occurredOn,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function saveEvent(Uuid $aggregateId, string $eventType, array $payload, \DateTimeImmutable $occurredOn): void
    {
        $version = $this->nextVersionForAggregate($aggregateId);

        try {
            $storedEvent = StoredEvent::commit(
                aggregateId: $aggregateId,
                eventType: $eventType,
                payload: $payload,
                version: $version,
                occurredOn: $occurredOn,
            );

            $this->mongoStore->saveEvent($storedEvent);
        } catch (ConcurrencyException) {
            $storedEvent = StoredEvent::commit(
                aggregateId: $aggregateId,
                eventType: $eventType,
                payload: $payload,
                version: $this->nextVersionForAggregate($aggregateId),
                occurredOn: $occurredOn,
            );

            $this->mongoStore->saveEvent($storedEvent);
        }
    }

    private function alreadyPublishedByTypeAndCorrelation(
        Uuid $aggregateId,
        string $eventType,
        string $correlationId
    ): bool
    {
        foreach ($this->mongoStore->findAllEventsByAggregateId($aggregateId) as $storedEvent) {
            if ($storedEvent->eventType !== $eventType) {
                continue;
            }

            $storedCorrelationId = $storedEvent->payload['correlationId'] ?? null;
            if (!is_string($storedCorrelationId)) {
                continue;
            }

            if ($storedCorrelationId === $correlationId) {
                return true;
            }
        }

        return false;
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
