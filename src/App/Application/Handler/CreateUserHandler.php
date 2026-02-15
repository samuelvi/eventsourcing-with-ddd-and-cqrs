<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\CreateUserCommand;
use App\Domain\Model\User;
use App\Domain\Repository\UserEventStoreRepositoryInterface;
use App\Domain\Shared\Constants;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Lock\LockFactory;

#[AsMessageHandler]
final readonly class CreateUserHandler
{
    private const USER_NAMESPACE = '6ba7b812-9bad-11d1-80b4-00c04fd430c8'; // Namespace OID

    public function __construct(
        private UserEventStoreRepositoryInterface $userRepository,
        private LockFactory $lockFactory,
        private MessageBusInterface $eventBus,
    ) {}

    public function __invoke(CreateUserCommand $command): void
    {
        $email = strtolower(trim($command->email));
        // Deterministic ID based on email
        $aggregateId = Uuid::v5(Uuid::fromString(Constants::USER_NAMESPACE), $email);
        
        $lock = $this->lockFactory->createLock('user_creation_' . $aggregateId->toRfc4122());

        if (!$lock->acquire(true)) {
            return;
        }

        $events = [];
        try {
            // Check if user already exists in Event Store
            $user = $this->userRepository->get($aggregateId);

            if ($user) {
                return;
            }

            // 1. Create the Aggregate (it records the UserRegistered event)
            $user = User::register(
                $aggregateId,
                $command->name,
                $email
            );

            $events = $user->getRecordedEvents();

            // 2. Persist Aggregate (saves events to Mongo)
            $this->userRepository->save($user);

        } finally {
            $lock->release();
        }

        // 3. Dispatch events after lock is released to avoid deadlocks with projections
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
