<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\ValueObject\Address;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\PersonName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Uid\Uuid;

final class CreateUserCommand
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public readonly string $id,

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        public readonly string $name,

        #[Assert\NotBlank]
        #[Assert\Email]
        public readonly string $email,

        #[Assert\Length(max: 255)]
        public readonly ?string $address = null,
    ) {}

    public function aggregateId(): Uuid
    {
        return Uuid::fromString($this->id);
    }

    public function nameVO(): PersonName
    {
        return PersonName::fromString($this->name);
    }

    public function emailVO(): Email
    {
        return Email::fromString($this->email);
    }

    public function addressVO(): ?Address
    {
        return Address::fromNullable($this->address);
    }
}
