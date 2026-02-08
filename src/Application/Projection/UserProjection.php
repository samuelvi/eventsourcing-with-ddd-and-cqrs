<?php

declare(strict_types=1);

namespace App\Application\Projection;

use App\Domain\Event\BookingWizardCompleted;
use App\Domain\Model\UserEntity;
use App\Domain\Model\ProjectionCheckpoint;
use App\Domain\Repository\UserWriteRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use App\Infrastructure\Persistence\Doctrine\WriteEntityManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class UserProjection
{
    public function __construct(
        private ReadEntityManager $readEntityManager,
        private WriteEntityManager $writeEntityManager,
        private UserWriteRepositoryInterface $userRepository,
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
            $existingUser = $this->readEntityManager->fetchOne(
                'SELECT id FROM users WHERE email = :email',
                ['email' => $event->clientEmail]
            );

            if (!$existingUser) {
                $user = UserEntity::create($event->clientName, $event->clientEmail);
                $this->userRepository->save($user);
            }

            // Update Checkpoint
            $checkpoint = $this->writeEntityManager->find(ProjectionCheckpoint::class, 'user_projection');
            if (!$checkpoint) {
                $checkpoint = new ProjectionCheckpoint('user_projection');
                $this->writeEntityManager->persist($checkpoint);
            }
            // We assume the event carries its ID or we find it. 
            // For now, let's use the bookingId as a proxy for the last processed event 
            // or better, if we can pass the StoredEvent ID somehow.
            // Since Messenger only carries the Domain Event, and the Domain Event doesn't have the StoredEvent ID yet...
            // Let's use the bookingId (which is unique) as our "high water mark".
            $checkpoint->update(Uuid::fromString($event->bookingId));
            $this->writeEntityManager->flush();

        } finally {
            $lock->release();
        }
    }
}
