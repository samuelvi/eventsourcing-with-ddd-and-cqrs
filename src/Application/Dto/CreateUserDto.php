<?php

declare(strict_types=1);

namespace App\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateUserDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        public string $name,

        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
    ) {}
}
