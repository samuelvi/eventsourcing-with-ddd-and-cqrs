<?php

declare(strict_types=1);

namespace App\Application\Projection;

use App\Domain\Event\QuoteRequested;
use App\Domain\Model\QuoteEntity;
use App\Domain\Repository\QuoteReadRepositoryInterface;
use App\Domain\Repository\QuoteWriteRepositoryInterface;
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
        // Idempotency Check
        if ($this->readRepository->exists($event->quoteId)) {
            return;
        }

        // Create Read Model
        $quote = QuoteEntity::hydrate(
            id: Uuid::fromString($event->quoteId),
            bookingId: Uuid::fromString($event->bookingId),
            supplierId: Uuid::fromString($event->supplierId),
            menuId: Uuid::fromString($event->menuId),
            price: $event->requestedPrice,
            createdAt: $event->occurredOn
        );

        $this->writeRepository->save($quote);

        // Update Checkpoint
        $this->updateCheckpoint($event->quoteId);
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
