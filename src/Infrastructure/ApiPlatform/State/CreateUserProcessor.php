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
        $id = Uuid::v7();
        
        $command = new CreateUserCommand(
            id: $id->toRfc4122(),
            name: $data->name,
            email: $data->email
        );

        $this->commandBus->dispatch($command);

        // We return a "Shell" entity. 
        // Note: The actual entity might not be in DB yet due to async projection.
        // But for the API response, we return what we know.
        return UserEntity::hydrate(
            name: $data->name,
            email: $data->email,
            id: $id
        );
    }
}
