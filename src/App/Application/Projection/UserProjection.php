<?php

declare(strict_types=1);

namespace App\Application\Projection;

use App\Domain\Event\UserRegistered;
use App\Domain\Event\UserProfileUpdated;
use App\Domain\Event\UserDeleted;
use App\Domain\Event\BookingWizardCompleted;
use App\Domain\Model\UserEntity;
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
        private MongoStore $mongoStore,
        private CacheInterface $cache,
    ) {}

    #[AsMessageHandler(priority: 10)]
    public function onUserRegistered(UserRegistered $event): void
    {
        $this->handleUserPersistence(
            $event->userId,
            $event->name,
            $event->email,
            $event->occurredOn
        );
    }

    #[AsMessageHandler(priority: 10)]
    public function onBookingWizardCompleted(BookingWizardCompleted $event): void
    {
        $this->handleUserPersistence(
            $event->userId,
            $event->clientName,
            $event->clientEmail,
            $event->occurredOn
        );
    }

    #[AsMessageHandler(priority: 10)]
    public function onUserProfileUpdated(UserProfileUpdated $event): void
    {
        if (!$this->isProjectionEnabled()) {
            return;
        }

        $user = $this->userWriteRepository->find($event->userId);
        if (!$user) {
            $user = UserEntity::hydrate(
                name: $event->name,
                email: $event->email,
                id: Uuid::fromString($event->userId),
                createdAt: $event->occurredOn
            );
        } else {
            $user->name = $event->name;
            $user->email = $event->email;
            $user->createdAt ??= $event->occurredOn;
        }

        $this->userWriteRepository->save($user);
        $this->updateCheckpoint($event->userId);
    }

    #[AsMessageHandler(priority: 10)]
    public function onUserDeleted(UserDeleted $event): void
    {
        if (!$this->isProjectionEnabled()) {
            return;
        }

        $user = $this->userWriteRepository->find($event->userId);
        if ($user) {
            $this->userWriteRepository->remove($user);
        }

        $this->updateCheckpoint($event->userId);
    }

    private function handleUserPersistence(string $userId, string $name, string $email, \DateTimeImmutable $createdAt): void
    {
        if (!$this->isProjectionEnabled()) {
            return;
        }

        if (!$this->userWriteRepository->find($userId)) {
            $user = UserEntity::hydrate(
                name: $name,
                email: $email,
                id: Uuid::fromString($userId),
                createdAt: $createdAt
            );
            try {
                $this->userWriteRepository->save($user);
            } catch (\Throwable $e) {
                // Ignore duplicate key errors at the DB level
            }
        }

        // Update Checkpoint
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
