<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Command\GenerateQuotesCommand;
use App\Domain\Model\BookingEntity;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * @implements ProcessorInterface<BookingEntity, BookingEntity>
 */
final readonly class GenerateQuotesProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {}

    /**
     * @param BookingEntity $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BookingEntity
    {
        $command = new GenerateQuotesCommand($data->id->toRfc4122());
        $this->messageBus->dispatch($command, [new TransportNamesStamp(['derivations_events'])]);

        return $data;
    }
}
