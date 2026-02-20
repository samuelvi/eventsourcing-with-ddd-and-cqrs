<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Command\DeleteUserCommand;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class DeleteUserProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $userId = $this->resolveUserId($uriVariables['id'] ?? null);
        $this->commandBus->dispatch(new DeleteUserCommand($userId));
    }

    private function resolveUserId(mixed $id): string
    {
        if (is_string($id)) {
            return $id;
        }

        if ($id instanceof Uuid) {
            return $id->toRfc4122();
        }

        throw new \InvalidArgumentException('User id must be a string or UUID.');
    }
}
