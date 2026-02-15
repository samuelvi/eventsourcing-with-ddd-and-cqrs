<?php

declare(strict_types=1);

namespace App\Application\Projection;

use App\Domain\Event\UserRegistered;
use App\Domain\Event\BookingWizardCompleted;
use App\Domain\Model\UserEntity;
use App\Infrastructure\EventSourcing\ProjectionCheckpoint;
use App\Domain\Repository\UserWriteRepositoryInterface;
use App\Domain\Repository\UserReadRepositoryInterface;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Uid\Uuid;

final readonly class UserProjection
{
    public function __construct(
        private UserReadRepositoryInterface $userReadRepository,
        private UserWriteRepositoryInterface $userWriteRepository,
        private MongoStore $mongoStore,
        private LockFactory $lockFactory,
        private CacheInterface $cache,
    ) {}

    #[AsMessageHandler(priority: 10)]
    public function onUserRegistered(UserRegistered $event): void
    {
        $this->handleUserPersistence(
            $event->userId,
            $event->name,
            $event->email
        );
    }

    #[AsMessageHandler(priority: 10)]
    public function onBookingWizardCompleted(BookingWizardCompleted $event): void
    {
        $this->handleUserPersistence(
            $event->userId,
            $event->clientName,
            $event->clientEmail
        );
    }

    private function handleUserPersistence(string $userId, string $name, string $email): void
    {
        $enabled = $this->cache->get('demo_user_projections_enabled', fn() => true);
        if (!$enabled) {
            return;
        }

        $lock = $this->lockFactory->createLock('user_creation_' . $userId);

        if (!$lock->acquire(true)) {
            return;
        }

        try {
            if (!$this->userWriteRepository->find($userId)) {
                $user = UserEntity::hydrate(
                    name: $name,
                    email: $email,
                    id: Uuid::fromString($userId)
                );
                try {
                    $this->userWriteRepository->save($user);
                } catch (\Throwable $e) {
                    // Ignore duplicate key errors
                }
            }

            // Update Checkpoint
            $this->updateCheckpoint($userId);

        } finally {
            $lock->release();
        }
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
}