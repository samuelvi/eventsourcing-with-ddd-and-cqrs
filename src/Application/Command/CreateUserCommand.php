<?php

declare(strict_types=1);

namespace App\Application\Command;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateUserCommand
{
    public function __construct(
        public readonly string $id,

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        public readonly string $name,

        #[Assert\NotBlank]
        #[Assert\Email]
        public readonly string $email,
    ) {}
}
