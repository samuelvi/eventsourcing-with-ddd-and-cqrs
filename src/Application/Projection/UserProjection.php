<?php

declare(strict_types=1);

namespace App\Application\Projection;

use App\Domain\Event\BookingWizardCompleted;
use App\Domain\Model\UserEntity;
use App\Domain\Repository\UserWriteRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Lock\LockFactory;

#[AsMessageHandler]
final readonly class UserProjection
{
    public function __construct(
        private ReadEntityManager $readEntityManager,
        private UserWriteRepositoryInterface $userRepository,
        private LockFactory $lockFactory,
    ) {}

    public function __invoke(BookingWizardCompleted $event): void
    {
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

            if ($existingUser) {
                return;
            }

            $user = UserEntity::create($event->clientName, $event->clientEmail);
            $this->userRepository->save($user);
        } finally {
            $lock->release();
        }
    }
}
