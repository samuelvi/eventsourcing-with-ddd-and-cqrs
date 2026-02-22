<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Messenger\Fixtures;

final readonly class TestMessage
{
    public function __construct(public string $content) {}
}
