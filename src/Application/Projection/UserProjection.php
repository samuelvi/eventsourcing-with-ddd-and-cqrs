<?php

declare(strict_types=1);

namespace App\Application\Projection;

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

#[AsMessageHandler]
final readonly class UserProjection
{
    public function __construct(
        private UserReadRepositoryInterface $userReadRepository,
        private UserWriteRepositoryInterface $userWriteRepository,
        private MongoStore $mongoStore,
        private LockFactory $lockFactory,
        private CacheInterface $cache,
    ) {}

    public function __invoke(BookingWizardCompleted $event): void
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
                $user = UserEntity::create($event->clientName, $event->clientEmail);
                $this->userWriteRepository->save($user);
            }

            // Update Checkpoint in Mongo
            $checkpoint = $this->mongoStore->findCheckpoint('user_projection');
            if (!$checkpoint) {
                $checkpoint = ProjectionCheckpoint::create('user_projection');
            }
            $checkpoint->update(Uuid::fromString($event->bookingId));
            $this->mongoStore->saveCheckpoint($checkpoint);

        } finally {
            $lock->release();
        }
    }
}
