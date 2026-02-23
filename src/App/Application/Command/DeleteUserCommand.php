<?php

declare(strict_types=1);

namespace App\Application\Command;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Uid\Uuid;

final readonly class DeleteUserCommand
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $id,
    ) {}

    public function aggregateId(): Uuid
    {
        return Uuid::fromString($this->id);
    }
}
