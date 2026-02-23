<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\CreateUserCommand;
use App\Domain\Exception\ConcurrencyException;
use App\Domain\Model\User;
use App\Domain\Repository\UserEventStoreRepositoryInterface;
use App\Domain\ValueObject\Address;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\PersonName;
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
        $aggregateId = Uuid::fromString($command->id);

        $user = User::register(
            $aggregateId,
            PersonName::fromString($command->name),
            Email::fromString($command->email),
            Address::fromNullable($command->address),
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
