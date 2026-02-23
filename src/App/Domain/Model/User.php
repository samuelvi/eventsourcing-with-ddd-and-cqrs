<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Event\UserRegistered;
use App\Domain\Event\UserProfileUpdated;
use App\Domain\Event\UserDeleted;
use App\Domain\Shared\TypeAssert;
use App\Domain\ValueObject\Address;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\PersonName;
use Symfony\Component\Uid\Uuid;

final class User extends AggregateRoot
{
    private PersonName $name;
    private Email $email;
    private ?Address $address = null;
    private bool $deleted = false;

    public static function register(Uuid $id, PersonName $name, Email $email, ?Address $address = null): self
    {
        $user = new self($id);
        $user->recordThat(new UserRegistered(
            userId: $id->toRfc4122(),
            name: $name->toString(),
            email: $email->toString(),
            occurredOn: new \DateTimeImmutable(),
            address: $address?->toString(),
        ));

        return $user;
    }

    protected function apply(object $event): void
    {
        if ($event instanceof UserRegistered) {
            $this->name = PersonName::fromString($event->name);
            $this->email = Email::fromString($event->email);
            $this->address = Address::fromNullable($event->address);
            $this->deleted = false;
            return;
        }

        if ($event instanceof UserProfileUpdated) {
            $this->name = PersonName::fromString($event->name);
            $this->email = Email::fromString($event->email);
            $this->address = Address::fromNullable($event->address);
            return;
        }

        if ($event instanceof UserDeleted) {
            $this->deleted = true;
        }
    }

    public function updateProfile(PersonName $name, Email $email, ?Address $address = null): void
    {
        $this->guardNotDeleted();

        $this->recordThat(new UserProfileUpdated(
            userId: $this->aggregateId->toRfc4122(),
            name: $name->toString(),
            email: $email->toString(),
            occurredOn: new \DateTimeImmutable(),
            address: $address?->toString(),
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
            'name' => $this->name->toString(),
            'email' => $this->email->toString(),
            'address' => $this->address?->toString(),
            'deleted' => $this->deleted,
        ];
    }

    protected function applySnapshot(array $state): void
    {
        $this->name = PersonName::fromString(TypeAssert::string($state['name'] ?? null));
        $this->email = Email::fromString(TypeAssert::string($state['email'] ?? null));
        $this->address = Address::fromNullable(
            isset($state['address']) && is_string($state['address']) ? $state['address'] : null
        );
        $this->deleted = (bool) ($state['deleted'] ?? false);
    }

    // Getters para lógica interna si fuera necesaria
    public function getName(): string
    {
        return $this->name->toString();
    }

    public function getEmail(): string
    {
        return $this->email->toString();
    }

    public function getAddress(): ?string
    {
        return $this->address?->toString();
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }
}
