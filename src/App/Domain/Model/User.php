<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Event\UserRegistered;
use App\Domain\Shared\TypeAssert;
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

    public function getSnapshotState(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    protected function applySnapshot(array $state): void
    {
        $this->name = TypeAssert::string($state['name'] ?? null);
        $this->email = TypeAssert::string($state['email'] ?? null);
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
