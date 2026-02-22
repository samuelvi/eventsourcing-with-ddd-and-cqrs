<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Messenger\Fixtures;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TestMessageHandler
{
    public static ?TestMessage $lastMessage = null;

    public function __invoke(TestMessage $message): void
    {
        self::$lastMessage = $message;
    }
}
