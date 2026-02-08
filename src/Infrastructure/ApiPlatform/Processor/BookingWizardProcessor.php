<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Command\SubmitBookingWizardCommand;
use App\Application\Dto\BookingWizardDto;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<BookingWizardDto, void>
 */
final readonly class BookingWizardProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof BookingWizardDto) {
            return;
        }

        $this->commandBus->dispatch(new SubmitBookingWizardCommand(
            id: $data->id,
            pax: $data->pax,
            budget: $data->budget,
            clientName: $data->clientName,
            clientEmail: $data->clientEmail
        ));
    }
}
