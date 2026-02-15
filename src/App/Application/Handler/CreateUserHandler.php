<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\CreateUserCommand;
use App\Domain\Exception\ConcurrencyException;
use App\Domain\Model\User;
use App\Domain\Repository\UserEventStoreRepositoryInterface;
use App\Domain\Shared\Constants;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class CreateUserHandler
{
    public function __construct(
        private UserEventStoreRepositoryInterface $userRepository,
        private MessageBusInterface $eventBus,
    ) {}

    public function __invoke(CreateUserCommand $command): void
    {
        $email = strtolower(trim($command->email));
        // Deterministic ID based on email
        $aggregateId = Uuid::v5(Uuid::fromString(Constants::USER_NAMESPACE), $email);

        // 1. Create the Aggregate (it records the UserRegistered event)
        $user = User::register(
            $aggregateId,
            $command->name,
            $email
        );

        $events = $user->getRecordedEvents();

        try {
            // 2. Persist Aggregate (saves events to Mongo)
            $this->userRepository->save($user);
        } catch (ConcurrencyException) {
            // User already registered
            return;
        }

        // 3. Dispatch events
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
