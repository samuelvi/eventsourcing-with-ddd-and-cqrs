<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\CreateProductCommand;
use App\Domain\Model\Product;
use App\Domain\Repository\ProductEventStoreRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class CreateProductHandler
{
    public function __construct(
        private ProductEventStoreRepositoryInterface $productRepository,
        private MessageBusInterface $eventBus,
    ) {}

    public function __invoke(CreateProductCommand $command): void
    {
        // 1. Create the Aggregate (it records the ProductRegistered event)
        $product = Product::register(
            id: \Symfony\Component\Uid\Uuid::v7(),
            name: $command->nameVO(),
            price: $command->priceVO(),
            type: $command->typeVO(),
            supplierId: $command->supplierIdVO(),
            details: $command->detailsData
        );

        $events = $product->getRecordedEvents();

        // 2. Persist to Event Store
        $this->productRepository->save($product);

        // 3. Dispatch events after persistence
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
