<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\DeleteUserCommand;
use App\Domain\Exception\ConcurrencyException;
use App\Domain\Model\User;
use App\Domain\Repository\UserEventStoreRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class DeleteUserHandler
{
    public function __construct(
        private UserEventStoreRepositoryInterface $userRepository,
        private MessageBusInterface $eventBus,
    ) {}

    public function __invoke(DeleteUserCommand $command): void
    {
        $aggregate = $this->userRepository->get($command->aggregateId());

        if (!$aggregate instanceof User) {
            throw new NotFoundHttpException(sprintf('User %s not found.', $command->id));
        }

        $aggregate->delete();
        $events = $aggregate->getRecordedEvents();

        try {
            $this->userRepository->save($aggregate);
        } catch (ConcurrencyException) {
            throw new \RuntimeException('Concurrent deletion detected for user aggregate.');
        }

        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
