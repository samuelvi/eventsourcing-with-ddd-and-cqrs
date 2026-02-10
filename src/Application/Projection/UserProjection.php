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

    #[AsMessageHandler]
    public function onUserRegistered(UserRegistered $event): void
    {
        $enabled = $this->cache->get('demo_user_projections_enabled', fn() => true);
        if (!$enabled) {
            return;
        }

        $lock = $this->lockFactory->createLock('user_creation_' . $event->userId);

        if (!$lock->acquire(true)) {
            return;
        }

        try {
            if (!$this->userReadRepository->exists($event->userId)) {
                $user = UserEntity::hydrate(
                    name: $event->name,
                    email: $event->email,
                    id: Uuid::fromString($event->userId)
                );
                $this->userWriteRepository->save($user);
            }

            // Update Checkpoint
            $this->updateCheckpoint($event->userId);

        } finally {
            $lock->release();
        }
    }

    #[AsMessageHandler]
    public function onBookingWizardCompleted(BookingWizardCompleted $event): void
    {
        // DEMO MODE: Check if user projections are enabled
        $enabled = $this->cache->get('demo_user_projections_enabled', fn() => true);
        if (!$enabled) {
            return;
        }

        $lock = $this->lockFactory->createLock('user_creation_' . $event->clientEmail);

        if (!$lock->acquire(true)) {
            return;
        }

        try {
            // Check if user exists (Read Side)
            if (!$this->userReadRepository->existsByEmail($event->clientEmail)) {
                // Legacy behavior: We don't have ID, so we create one (UserEntity::create generates v7)
                // But wait, UserEntity::create uses named constructor?
                // Let's check UserEntity methods. It has create? 
                // Previous code used UserEntity::create($name, $email).
                // But UserEntity in my read_file earlier only showed `hydrate` and `__construct`.
                // It uses `NamedConstructorTrait`. Let's assume `create` comes from there or I missed it.
                // Ah, I need to check NamedConstructorTrait or just use hydrate with a new UUID.
                
                // Let's assume UserEntity::hydrate with a new UUID is safer if create() is missing.
                $user = UserEntity::hydrate(
                    name: $event->clientName, 
                    email: $event->clientEmail, 
                    id: Uuid::v7()
                );
                $this->userWriteRepository->save($user);
            }

            // Update Checkpoint in Mongo
            $this->updateCheckpoint($event->bookingId);

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