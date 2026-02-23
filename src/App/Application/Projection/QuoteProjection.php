<?php

declare(strict_types=1);

namespace App\Application\Projection;

use App\Domain\Event\QuoteRequested;
use App\Domain\Model\QuoteEntity;
use App\Domain\Repository\QuoteReadRepositoryInterface;
use App\Domain\Repository\QuoteWriteRepositoryInterface;
use App\Domain\ValueObject\NonNegativeAmount;
use App\Domain\ValueObject\UuidString;
use App\Infrastructure\EventSourcing\ProjectionCheckpoint;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class QuoteProjection
{
    public function __construct(
        private QuoteWriteRepositoryInterface $writeRepository,
        private QuoteReadRepositoryInterface $readRepository,
        private MongoStore $mongoStore,
    ) {}

    public function __invoke(QuoteRequested $event): void
    {
        $quoteId = UuidString::fromString($event->quoteId);

        // Idempotency Check
        if ($this->readRepository->exists($quoteId->toString())) {
            return;
        }

        $bookingId = UuidString::fromString($event->bookingId);
        $supplierId = UuidString::fromString($event->supplierId);
        $productId = UuidString::fromString($event->productId);
        $price = NonNegativeAmount::fromFloat($event->requestedPrice);

        // Create Read Model
        $quote = QuoteEntity::hydrate(
            id: Uuid::fromString($quoteId->toString()),
            bookingId: Uuid::fromString($bookingId->toString()),
            supplierId: Uuid::fromString($supplierId->toString()),
            productId: Uuid::fromString($productId->toString()),
            price: $price->toFloat(),
            createdAt: $event->occurredOn
        );

        $this->writeRepository->save($quote);

        // Update Checkpoint
        $this->updateCheckpoint($quoteId->toString());
    }

    private function updateCheckpoint(string $lastEventId): void
    {
        $checkpoint = $this->mongoStore->findCheckpoint('quote_projection');
        if (!$checkpoint) {
            $checkpoint = ProjectionCheckpoint::create('quote_projection');
        }
        $checkpoint->update(Uuid::fromString($lastEventId));
        $this->mongoStore->saveCheckpoint($checkpoint);
    }
}
