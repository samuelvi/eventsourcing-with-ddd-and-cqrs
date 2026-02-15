<?php

declare(strict_types=1);

namespace App\Domain\Event;

final readonly class UserRegistered
{
    public function __construct(
        public string $userId,
        public string $name,
        public string $email,
        public \DateTimeImmutable $occurredOn
    ) {}
}
