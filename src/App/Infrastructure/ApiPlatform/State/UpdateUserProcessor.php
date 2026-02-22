<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Bus\SyncCommandBusInterface;
use App\Application\Command\UpdateUserCommand;
use App\Application\Dto\UpdateUserDto;
use App\Domain\Model\UserEntity;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<UpdateUserDto, UserEntity>
 */
final readonly class UpdateUserProcessor implements ProcessorInterface
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
    ) {}

    /**
     * @param UpdateUserDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserEntity
    {
        $userId = $this->resolveUserId($uriVariables['id'] ?? null);

        $command = new UpdateUserCommand(
            id: $userId,
            name: $data->name,
            email: $data->email
        );

        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $exception) {
            if ($this->containsWrapped($exception, ConflictHttpException::class)) {
                throw new ConflictHttpException('Email is already in use by another user.', $exception);
            }

            if ($this->containsWrapped($exception, UniqueConstraintViolationException::class)) {
                throw new ConflictHttpException('Email is already in use by another user.', $exception);
            }

            throw $exception;
        }

        return UserEntity::hydrate(
            name: trim($data->name),
            email: strtolower(trim($data->email)),
            id: Uuid::fromString($userId),
            createdAt: null
        );
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

    private function containsWrapped(\Throwable $exception, string $className): bool
    {
        if ($exception instanceof $className) {
            return true;
        }

        if ($exception instanceof HandlerFailedException) {
            foreach ($exception->getWrappedExceptions() as $wrapped) {
                if ($this->containsWrapped($wrapped, $className)) {
                    return true;
                }
            }
        }

        $previous = $exception->getPrevious();
        if ($previous !== null) {
            return $this->containsWrapped($previous, $className);
        }

        return false;
    }
}
