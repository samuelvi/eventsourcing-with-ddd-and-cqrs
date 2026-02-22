<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\CreateUserCommand;
use App\Domain\Exception\ConcurrencyException;
use App\Domain\Model\User;
use App\Domain\Repository\UserEventStoreRepositoryInterface;
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
        $aggregateId = Uuid::fromString($command->id);

        $user = User::register(
            $aggregateId,
            $command->name,
            $email,
            $this->normalizeAddress($command->address),
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

    private function normalizeAddress(?string $address): ?string
    {
        if ($address === null) {
            return null;
        }

        $normalized = trim($address);

        return $normalized === '' ? null : $normalized;
    }
}
