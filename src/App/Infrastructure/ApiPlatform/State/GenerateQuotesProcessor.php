<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Command\Quotes\GenerateQuotesCommand;
use App\Application\Service\DerivationRunContextFactory;
use App\Application\Service\DerivationRunTracker;
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
        private DerivationRunContextFactory $derivationRunContextFactory,
        private DerivationRunTracker $derivationRunTracker,
    ) {}

    /**
     * @param BookingEntity $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BookingEntity
    {
        $derivationContext = $this->derivationRunContextFactory->create($data->id->toRfc4122());
        $this->derivationRunTracker->open($derivationContext);

        $command = new GenerateQuotesCommand(
            bookingId: $derivationContext->bookingId,
            derivationRunId: $derivationContext->derivationRunId,
            correlationId: $derivationContext->correlationId,
        );
        $this->messageBus->dispatch($command, [new TransportNamesStamp(['derivations_events'])]);

        return $data;
    }
}
