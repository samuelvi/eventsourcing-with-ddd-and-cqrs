<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use App\Infrastructure\EventSourcing\StoredEvent;
use App\Infrastructure\EventSourcing\Snapshot;
use App\Infrastructure\EventSourcing\ProjectionCheckpoint;

/**
 * @implements ProviderInterface<object>
 */
final readonly class MongoStoreProvider implements ProviderInterface
{
    public function __construct(
        private MongoStore $mongoStore,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $resourceClass = $operation->getClass();

        if ($resourceClass === StoredEvent::class) {
            return $this->mongoStore->findEvents();
        }

        if ($resourceClass === Snapshot::class) {
            return $this->mongoStore->findSnapshots();
        }

        if ($resourceClass === ProjectionCheckpoint::class) {
            return $this->mongoStore->findAllCheckpoints();
        }

        return null;
    }
}
