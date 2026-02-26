<?php

declare(strict_types=1);

namespace App\Application\Command\Quotes;

use App\Domain\ValueObject\UuidString;

final readonly class GenerateQuotesCommand
{
    public function __construct(
        public string $bookingId,
        public ?string $correlationId = null,
    ) {}

    public function bookingIdVO(): UuidString
    {
        return UuidString::fromString($this->bookingId);
    }
}
