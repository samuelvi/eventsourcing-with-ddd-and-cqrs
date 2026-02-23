<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\CreateUserCommand;
use App\Domain\Exception\ConcurrencyException;
use App\Domain\Model\User;
use App\Domain\Repository\UserEventStoreRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class CreateUserHandler
{
    public function __construct(
        private UserEventStoreRepositoryInterface $userRepository,
        private MessageBusInterface $eventBus,
    ) {}

    public function __invoke(CreateUserCommand $command): void
    {
        $user = User::register(
            $command->aggregateId(),
            $command->nameVO(),
            $command->emailVO(),
            $command->addressVO(),
        );

        $events = $user->getRecordedEvents();

        try {
            $this->userRepository->save($user);
        } catch (ConcurrencyException) {
            return;
        }

        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
