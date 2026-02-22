<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Bus;

use App\Application\Bus\AsyncCommandBusInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final readonly class MessengerAsyncCommandBus implements AsyncCommandBusInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {}

    public function dispatch(object $command): void
    {
        $this->messageBus->dispatch($command, [new TransportNamesStamp(['async'])]);
    }
}

