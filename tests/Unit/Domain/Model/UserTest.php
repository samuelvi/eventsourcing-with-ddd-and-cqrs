<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model;

use App\Domain\Event\UserDeleted;
use App\Domain\Event\UserProfileUpdated;
use App\Domain\Event\UserRegistered;
use App\Domain\Model\User;
use App\Domain\ValueObject\Address;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\PersonName;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class UserTest extends TestCase
{
    public function testUpdateProfileRecordsEventAndUpdatesState(): void
    {
        $id = Uuid::v7();
        $user = User::register($id, PersonName::fromString('Old Name'), Email::fromString('old@example.com'));
        $user->clearRecordedEvents();

        $user->updateProfile(
            PersonName::fromString('New Name'),
            Email::fromString('new@example.com')
        );

        $events = $user->getRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserProfileUpdated::class, $events[0]);
        $this->assertSame('New Name', $user->getName());
        $this->assertSame('new@example.com', $user->getEmail());
    }

    public function testDeleteRecordsEventAndPreventsFutureChanges(): void
    {
        $id = Uuid::v7();
        $user = User::register($id, PersonName::fromString('Ada'), Email::fromString('ada@example.com'));
        $user->clearRecordedEvents();

        $user->delete();

        $events = $user->getRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserDeleted::class, $events[0]);

        $this->expectException(\DomainException::class);
        $user->updateProfile(PersonName::fromString('Blocked'), Email::fromString('blocked@example.com'));
    }

    public function testReconstitutedDeletedUserCannotBeUpdated(): void
    {
        $id = Uuid::v7();
        $history = [
            new UserRegistered(
                userId: $id->toRfc4122(),
                name: 'Ada',
                email: 'ada@example.com',
                occurredOn: new \DateTimeImmutable('2026-02-19T10:00:00+00:00')
            ),
            new UserDeleted(
                userId: $id->toRfc4122(),
                occurredOn: new \DateTimeImmutable('2026-02-19T11:00:00+00:00')
            ),
        ];

        $user = User::reconstituteFromHistory($id, $history);

        $this->expectException(\DomainException::class);
        $user->updateProfile(
            PersonName::fromString('After Delete'),
            Email::fromString('after@example.com'),
            Address::fromNullable('Road 1')
        );
    }
}
