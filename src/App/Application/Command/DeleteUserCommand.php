<?php

declare(strict_types=1);

namespace App\Application\Command;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DeleteUserCommand
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $id,
    ) {}
}
