<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Transport\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final readonly class KafkaReceivedStamp implements StampInterface
{
    public function __construct(public object $message) {}
}

