<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Messenger\Fixtures;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TestFailingMessageHandler
{
    public function __invoke(TestFailingMessage $message): void
    {
        throw new \RuntimeException('Fallo simulado para DLQ');
    }
}
