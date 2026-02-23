<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\UpdateUserCommand;
use App\Domain\Exception\ConcurrencyException;
use App\Domain\Model\User;
use App\Domain\Repository\UserEventStoreRepositoryInterface;
use App\Domain\Repository\UserReadRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class UpdateUserHandler
{
    public function __construct(
        private UserEventStoreRepositoryInterface $userRepository,
        private MessageBusInterface $eventBus,
        private UserReadRepositoryInterface $userReadRepository,
    ) {}

    public function __invoke(UpdateUserCommand $command): void
    {
        $aggregate = $this->userRepository->get($command->aggregateId());

        if (!$aggregate instanceof User) {
            throw new NotFoundHttpException(sprintf('User %s not found.', $command->id));
        }

        $nextEmail = $command->emailVO();
        $duplicate = $this->userReadRepository->findByEmail($nextEmail->toString());
        if ($duplicate !== null && $duplicate['id'] !== $command->id) {
            throw new ConflictHttpException('Email is already in use by another user.');
        }

        $aggregate->updateProfile(
            name: $command->nameVO(),
            email: $nextEmail,
            address: $command->addressVO()
        );

        $events = $aggregate->getRecordedEvents();

        try {
            $this->userRepository->save($aggregate);
        } catch (ConcurrencyException) {
            throw new \RuntimeException('Concurrent update detected for user aggregate.');
        }

        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
