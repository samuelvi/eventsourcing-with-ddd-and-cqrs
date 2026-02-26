<?php

declare(strict_types=1);

namespace App\Application\Handler\Quotes;

use App\Domain\Derivation\Candidate\QuoteCandidate;
use App\Domain\Event\QuoteRequested;
use App\Domain\ValueObject\NonNegativeAmount;
use App\Domain\ValueObject\UuidString;
use App\Infrastructure\EventSourcing\StoredEvent;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final readonly class QuoteRequestedPublisher
{
    public function __construct(
        private MongoStore $mongoStore,
        private MessageBusInterface $eventBus,
    ) {}

    public function publish(string $bookingId, QuoteCandidate $candidate, \DateTimeImmutable $occurredOn): void
    {
        $quoteId = Uuid::v7();
        $supplierId = UuidString::fromString($candidate->supplierId);
        $productId = UuidString::fromString($candidate->productId);
        $requestedPrice = NonNegativeAmount::fromFloat($candidate->price);

        $quoteEvent = new QuoteRequested(
            quoteId: $quoteId->toRfc4122(),
            bookingId: $bookingId,
            supplierId: $supplierId->toString(),
            productId: $productId->toString(),
            requestedPrice: $requestedPrice->toFloat(),
            occurredOn: $occurredOn
        );

        $storedEvent = StoredEvent::commit(
            aggregateId: $quoteId,
            eventType: QuoteRequested::class,
            payload: [
                'quoteId' => $quoteId->toRfc4122(),
                'bookingId' => $bookingId,
                'supplierId' => $supplierId->toString(),
                'productId' => $productId->toString(),
                'requestedPrice' => $requestedPrice->toFloat(),
                'occurredOn' => $occurredOn->format(\DateTimeInterface::ATOM)
            ],
            occurredOn: $occurredOn
        );

        $this->mongoStore->saveEvent($storedEvent);
        $this->eventBus->dispatch($quoteEvent);
    }
}
