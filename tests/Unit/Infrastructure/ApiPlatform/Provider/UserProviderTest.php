<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Domain\Model\UserEntity;
use App\Domain\Repository\UserReadRepositoryInterface;
use App\Infrastructure\ApiPlatform\Provider\UserProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class UserProviderTest extends TestCase
{
    public function testProvideCollectionHydratesCreatedAtFromRepository(): void
    {
        $repository = new InMemoryUserReadRepository(
            [
                [
                    'id' => '01951b27-4f10-7a6c-aea9-d57dc89656f2',
                    'name' => 'Ada Lovelace',
                    'email' => 'ada@example.com',
                    'address' => null,
                    'created_at' => '2026-02-19 10:15:00',
                ],
            ],
            []
        );

        $provider = new UserProvider($repository);

        $result = $provider->provide(new GetCollection());

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(UserEntity::class, $result[0]);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result[0]->createdAt);
        $this->assertSame('2026-02-19T10:15:00+00:00', $result[0]->createdAt->format(\DateTimeInterface::ATOM));
    }

    public function testProvideItemHydratesCreatedAtFromRepository(): void
    {
        $repository = new InMemoryUserReadRepository(
            [],
            [
                '01951b27-4f10-7a6c-aea9-d57dc89656f2' => [
                    'id' => '01951b27-4f10-7a6c-aea9-d57dc89656f2',
                    'name' => 'Ada Lovelace',
                    'email' => 'ada@example.com',
                    'address' => null,
                    'created_at' => '2026-02-19 10:15:00',
                ],
            ]
        );

        $provider = new UserProvider($repository);

        $result = $provider->provide(new Get(), ['id' => '01951b27-4f10-7a6c-aea9-d57dc89656f2']);

        $this->assertNotNull($result);
        $this->assertInstanceOf(UserEntity::class, $result);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->createdAt);
        $this->assertSame('2026-02-19T10:15:00+00:00', $result->createdAt->format(\DateTimeInterface::ATOM));
    }

    public function testProvideItemAcceptsUuidUriVariable(): void
    {
        $id = Uuid::fromString('01951b27-4f10-7a6c-aea9-d57dc89656f2');
        $repository = new InMemoryUserReadRepository(
            [],
            [
                $id->toRfc4122() => [
                    'id' => $id->toRfc4122(),
                    'name' => 'Ada Lovelace',
                    'email' => 'ada@example.com',
                    'address' => null,
                    'created_at' => '2026-02-19 10:15:00',
                ],
            ]
        );

        $provider = new UserProvider($repository);

        $result = $provider->provide(new Get(), ['id' => $id]);

        $this->assertNotNull($result);
        $this->assertInstanceOf(UserEntity::class, $result);
        $this->assertSame($id->toRfc4122(), $result->id->toRfc4122());
    }
}

final readonly class InMemoryUserReadRepository implements UserReadRepositoryInterface
{
    /**
     * @param array<array{id: string, name: string, email: string, address: string|null, created_at: string}> $list
     * @param array<string, array{id: string, name: string, email: string, address: string|null, created_at: string}> $byId
     */
    public function __construct(
        private array $list,
        private array $byId,
    ) {}

    public function findAllForList(): array
    {
        return $this->list;
    }

    public function findById(string $id): ?array
    {
        return $this->byId[$id] ?? null;
    }

    public function countAll(): int
    {
        return count($this->list);
    }

    public function existsByEmail(string $email): bool
    {
        return false;
    }

    public function exists(string $id): bool
    {
        return false;
    }

    public function findByEmail(string $email): ?array
    {
        return null;
    }
}
