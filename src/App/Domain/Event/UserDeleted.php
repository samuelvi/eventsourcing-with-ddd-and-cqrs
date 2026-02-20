<?php

declare(strict_types=1);

namespace App\Domain\Event;

final readonly class UserDeleted
{
    public function __construct(
        public string $userId,
        public \DateTimeImmutable $occurredOn
    ) {}
}
