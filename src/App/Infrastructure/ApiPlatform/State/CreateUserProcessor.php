<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Bus\SyncCommandBusInterface;
use App\Application\Command\CreateUserCommand;
use App\Application\Dto\CreateUserDto;
use App\Domain\Model\UserEntity;
use App\Domain\ValueObject\Address;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\PersonName;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<CreateUserDto, UserEntity>
 */
final readonly class CreateUserProcessor implements ProcessorInterface
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
    ) {}

    /**
     * @param CreateUserDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserEntity
    {
        $id = Uuid::fromString($data->id);
        $name = PersonName::fromString($data->name);
        $email = Email::fromString($data->email);
        $address = Address::fromNullable($data->address);

        $command = new CreateUserCommand(
            id: $id->toRfc4122(),
            name: $name->toString(),
            email: $email->toString(),
            address: $address?->toString()
        );

        $this->commandBus->dispatch($command);

        return UserEntity::hydrate(
            name: $name->toString(),
            email: $email->toString(),
            id: $id,
            createdAt: new \DateTimeImmutable(),
            address: $address?->toString()
        );
    }
}
