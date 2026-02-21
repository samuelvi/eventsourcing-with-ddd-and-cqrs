<?php

declare(strict_types=1);

namespace App\Application\Projection;

use App\Domain\Event\UserRegistered;
use App\Domain\Event\UserProfileUpdated;
use App\Domain\Event\UserDeleted;
use App\Domain\Event\BookingWizardCompleted;
use App\Domain\Model\User;
use App\Domain\Model\UserEntity;
use App\Domain\Repository\UserEventStoreRepositoryInterface;
use App\Infrastructure\EventSourcing\ProjectionCheckpoint;
use App\Domain\Repository\UserWriteRepositoryInterface;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Uid\Uuid;

final readonly class UserProjection
{
    private const CACHE_KEY_MASTER = 'demo_projections_enabled';
    private const CACHE_KEY_USER = 'demo_user_projections_enabled';

    public function __construct(
        private UserWriteRepositoryInterface $userWriteRepository,
        private UserEventStoreRepositoryInterface $userEventStoreRepository,
        private MongoStore $mongoStore,
        private CacheInterface $cache,
    ) {}

    #[AsMessageHandler(priority: 10)]
    public function onUserRegistered(UserRegistered $event): void
    {
        $this->synchronizeFromAggregate($event->userId, $event->occurredOn);
    }

    #[AsMessageHandler(priority: 10)]
    public function onBookingWizardCompleted(BookingWizardCompleted $event): void
    {
        $this->synchronizeFromAggregate($event->userId, $event->occurredOn);
    }

    #[AsMessageHandler(priority: 10)]
    public function onUserProfileUpdated(UserProfileUpdated $event): void
    {
        $this->synchronizeFromAggregate($event->userId, $event->occurredOn);
    }

    #[AsMessageHandler(priority: 10)]
    public function onUserDeleted(UserDeleted $event): void
    {
        $this->synchronizeFromAggregate($event->userId, $event->occurredOn);
    }

    private function synchronizeFromAggregate(string $userId, \DateTimeImmutable $fallbackCreatedAt): void
    {
        if (!$this->isProjectionEnabled()) {
            return;
        }

        $aggregate = $this->userEventStoreRepository->get(Uuid::fromString($userId));
        $existing = $this->userWriteRepository->find($userId);

        if (!$aggregate instanceof User || $aggregate->isDeleted()) {
            if ($existing) {
                $this->userWriteRepository->remove($existing);
            }

            $this->updateCheckpoint($userId);
            return;
        }

        $projected = $existing ?? UserEntity::hydrate(
            name: $aggregate->getName(),
            email: $aggregate->getEmail(),
            id: Uuid::fromString($userId),
            createdAt: $fallbackCreatedAt
        );

        $projected->name = $aggregate->getName();
        $projected->email = $aggregate->getEmail();
        $projected->createdAt ??= $fallbackCreatedAt;

        $this->userWriteRepository->save($projected);
        $this->updateCheckpoint($userId);
    }

    private function updateCheckpoint(string $lastEventId): void
    {
        $checkpoint = $this->mongoStore->findCheckpoint('user_projection');
        if (!$checkpoint) {
            $checkpoint = ProjectionCheckpoint::create('user_projection');
        }
        $checkpoint->update(Uuid::fromString($lastEventId));
        $this->mongoStore->saveCheckpoint($checkpoint);
    }

    private function isProjectionEnabled(): bool
    {
        $master = $this->cache->get(self::CACHE_KEY_MASTER, fn() => true);
        $user = $this->cache->get(self::CACHE_KEY_USER, fn() => true);

        return $master && $user;
    }
}
