<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Event\UserRegistered;
use App\Domain\Event\UserProfileUpdated;
use App\Domain\Event\UserDeleted;
use App\Domain\Shared\TypeAssert;
use Symfony\Component\Uid\Uuid;

final class User extends AggregateRoot
{
    private string $name;
    private string $email;
    private bool $deleted = false;

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
            $this->deleted = false;
            return;
        }

        if ($event instanceof UserProfileUpdated) {
            $this->name = $event->name;
            $this->email = $event->email;
            return;
        }

        if ($event instanceof UserDeleted) {
            $this->deleted = true;
        }
    }

    public function updateProfile(string $name, string $email): void
    {
        $this->guardNotDeleted();

        $this->recordThat(new UserProfileUpdated(
            userId: $this->aggregateId->toRfc4122(),
            name: $name,
            email: strtolower(trim($email)),
            occurredOn: new \DateTimeImmutable()
        ));
    }

    public function delete(): void
    {
        $this->guardNotDeleted();

        $this->recordThat(new UserDeleted(
            userId: $this->aggregateId->toRfc4122(),
            occurredOn: new \DateTimeImmutable()
        ));
    }

    private function guardNotDeleted(): void
    {
        if ($this->deleted) {
            throw new \DomainException('Cannot modify a deleted user aggregate.');
        }
    }

    public function getSnapshotState(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'deleted' => $this->deleted,
        ];
    }

    protected function applySnapshot(array $state): void
    {
        $this->name = TypeAssert::string($state['name'] ?? null);
        $this->email = TypeAssert::string($state['email'] ?? null);
        $this->deleted = (bool) ($state['deleted'] ?? false);
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
