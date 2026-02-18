<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Model\User;
use App\Domain\Repository\UserEventStoreRepositoryInterface;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @extends EventSourcingRepository<User>
 */
final readonly class UserEventSourcingRepository extends EventSourcingRepository implements UserEventStoreRepositoryInterface
{
    public function __construct(
        MongoStore $mongoStore,
        SerializerInterface $serializer,
        int $snapshotThreshold,
    ) {
        parent::__construct(User::class, $mongoStore, $serializer, $snapshotThreshold);
    }
}
