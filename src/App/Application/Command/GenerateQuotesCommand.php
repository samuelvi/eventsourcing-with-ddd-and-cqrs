<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\ValueObject\UuidString;

final readonly class GenerateQuotesCommand
{
    public function __construct(
        public string $bookingId
    ) {}

    public function bookingIdVO(): UuidString
    {
        return UuidString::fromString($this->bookingId);
    }
}
