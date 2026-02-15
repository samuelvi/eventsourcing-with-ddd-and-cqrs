<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class ConcurrencyException extends \RuntimeException
{
    public static function versionMismatch(string $aggregateId, int $version): self
    {
        return new self(sprintf(
            'Concurrency detected: Event version %d for aggregate %s already exists.',
            $version,
            $aggregateId
        ));
    }
}
