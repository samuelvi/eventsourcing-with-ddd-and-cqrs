<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Bus\AsyncCommandBusInterface;
use App\Application\Command\GenerateQuotesCommand;
use App\Domain\Model\BookingEntity;

/**
 * @implements ProcessorInterface<BookingEntity, BookingEntity>
 */
final readonly class GenerateQuotesProcessor implements ProcessorInterface
{
    public function __construct(
        private AsyncCommandBusInterface $commandBus,
    ) {}

    /**
     * @param BookingEntity $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BookingEntity
    {
        $command = new GenerateQuotesCommand($data->id->toRfc4122());
        $this->commandBus->dispatch($command);

        return $data;
    }
}
