<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\GenerateQuotesCommand;
use App\Domain\Event\QuoteRequested;
use App\Domain\Repository\BookingReadRepositoryInterface;
use App\Domain\Repository\ProductReadRepositoryInterface;
use App\Infrastructure\EventSourcing\StoredEvent;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use App\Domain\Shared\TypeAssert;
use App\Domain\ValueObject\NonNegativeAmount;
use App\Domain\ValueObject\UuidString;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class GenerateQuotesHandler
{
    public function __construct(
        private BookingReadRepositoryInterface $bookingReadRepository,
        private ProductReadRepositoryInterface $productReadRepository,
        private MongoStore $mongoStore,
        private MessageBusInterface $eventBus,
    ) {}

    public function __invoke(GenerateQuotesCommand $command): void
    {
        $bookingRow = $this->bookingReadRepository->findById($command->bookingId);
        if (!$bookingRow) {
            return;
        }

        /** @var array<string, mixed> $bookingData */
        $bookingData = TypeAssert::array(json_decode(TypeAssert::string($bookingRow['data']), true));
        $budget = NonNegativeAmount::fromFloat(TypeAssert::float($bookingData['budget'] ?? 0.0));

        $matches = $this->productReadRepository->findByBudget($budget->toFloat());

        if (empty($matches)) {
            return;
        }

        $occurredOn = new \DateTimeImmutable();

        foreach ($matches as $product) {
            $quoteId = Uuid::v7();

            // 1. Create the Domain Event
            $supplierId = UuidString::fromString(TypeAssert::string($product['supplier_id']));
            $productId = UuidString::fromString(TypeAssert::string($product['id']));
            $requestedPrice = NonNegativeAmount::fromFloat((float) $product['price']);

            $quoteEvent = new QuoteRequested(
                quoteId: $quoteId->toRfc4122(),
                bookingId: $command->bookingId,
                supplierId: $supplierId->toString(),
                productId: $productId->toString(),
                requestedPrice: $requestedPrice->toFloat(),
                occurredOn: $occurredOn
            );

            // 2. Persist to Event Store (Mongo)
            $storedEvent = StoredEvent::commit(
                aggregateId: $quoteId,
                eventType: QuoteRequested::class,
                payload: [
                    'quoteId' => $quoteId->toRfc4122(),
                    'bookingId' => $command->bookingId,
                    'supplierId' => $supplierId->toString(),
                    'productId' => $productId->toString(),
                    'requestedPrice' => $requestedPrice->toFloat(),
                    'occurredOn' => $occurredOn->format(\DateTimeInterface::ATOM)
                ],
                occurredOn: $occurredOn
            );

            $this->mongoStore->saveEvent($storedEvent);

            // 3. Dispatch for Projections
            $this->eventBus->dispatch($quoteEvent);
        }
    }
}
