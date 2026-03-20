<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Event;

final readonly class QuoteRestartProcess
{
    /**
     * @param array<int, string> $excludedProductIds
     */
    public function __construct(
        public string $derivationRunId,
        public string $correlationId,
        public string $bookingId,
        public array $excludedProductIds = [],
        public \DateTimeImmutable $occurredOn = new \DateTimeImmutable(),
    ) {}
}
