<?php

declare(strict_types=1);

namespace App\Domain\Model;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Domain\Shared\TypeAssert;
use App\Infrastructure\ApiPlatform\Provider\MongoStoreProvider;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Plain Domain Object for StoredEvent (no longer a Doctrine entity)
 */
#[ApiResource(
    shortName: 'EventStore',
    operations: [
        new Get(uriTemplate: '/event-store/{id}', provider: MongoStoreProvider::class),
        new GetCollection(uriTemplate: '/event-store', provider: MongoStoreProvider::class, paginationEnabled: false)
    ],
    normalizationContext: ['groups' => ['event:read']]
)]
class StoredEvent
{
    private function __construct(
        #[Groups(['event:read'])]
        public readonly Uuid $aggregateId,
        #[Groups(['event:read'])]
        public readonly string $eventType,
        /** @var array<string, mixed> */
        #[Groups(['event:read'])]
        public readonly array $payload,
        #[Groups(['event:read'])]
        public readonly Uuid $id,
        #[Groups(['event:read'])]
        public readonly int $version = 1,
        #[Groups(['event:read'])]
        public readonly \DateTimeImmutable $occurredOn = new \DateTimeImmutable()
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function commit(
        Uuid $aggregateId,
        string $eventType,
        array $payload,
        int $version = 1,
        ?\DateTimeImmutable $occurredOn = null
    ): self {
        return new self(
            $aggregateId,
            $eventType,
            $payload,
            Uuid::v7(),
            $version,
            $occurredOn ?? new \DateTimeImmutable()
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Uuid::fromString(TypeAssert::string($data['aggregateId'])),
            TypeAssert::string($data['eventType']),
            TypeAssert::array($data['payload']),
            Uuid::fromString(TypeAssert::string($data['id'])),
            TypeAssert::int($data['version']),
            new \DateTimeImmutable(TypeAssert::string($data['occurredOn']))
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'aggregateId' => $this->aggregateId->toRfc4122(),
            'eventType' => $this->eventType,
            'payload' => $this->payload,
            'version' => $this->version,
            'occurredOn' => $this->occurredOn->format(\DateTimeInterface::ATOM)
        ];
    }
}