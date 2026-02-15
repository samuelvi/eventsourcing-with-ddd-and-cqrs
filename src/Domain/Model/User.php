<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Event\UserRegistered;
use Symfony\Component\Uid\Uuid;

final class User extends AggregateRoot
{
    private string $name;
    private string $email;

    public static function register(Uuid $id, string $name, string $email): self
    {
        $user = new self($id);
        $user->recordThat(new UserRegistered(
            userId: $id->toRfc4122(),
            name: $name,
            email: $email,
            occurredOn: new \DateTimeImmutable()
        ));

        return $user;
    }

    protected function apply(object $event): void
    {
        if ($event instanceof UserRegistered) {
            $this->name = $event->name;
            $this->email = $event->email;
        }
    }

    // Getters para lógica interna si fuera necesaria
    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
