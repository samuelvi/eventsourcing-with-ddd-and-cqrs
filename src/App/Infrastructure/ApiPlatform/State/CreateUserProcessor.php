<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Command\CreateUserCommand;
use App\Application\Dto\CreateUserDto;
use App\Domain\Model\UserEntity;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<CreateUserDto, UserEntity>
 */
final readonly class CreateUserProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {}

    /**
     * @param CreateUserDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserEntity
    {
        $id = Uuid::fromString($data->id);

        $command = new CreateUserCommand(
            id: $id->toRfc4122(),
            name: $data->name,
            email: $data->email
        );

        $this->commandBus->dispatch($command);

        return UserEntity::hydrate(
            name: trim($data->name),
            email: strtolower(trim($data->email)),
            id: $id,
            createdAt: new \DateTimeImmutable()
        );
    }
}
