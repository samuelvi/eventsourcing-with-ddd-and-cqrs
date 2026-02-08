<?php

declare(strict_types=1);

namespace App\Domain\Model;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
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
    public function __construct(
        #[Groups(['event:read'])]
        public readonly Uuid $aggregateId,
        #[Groups(['event:read'])]
        public readonly string $eventType,
        #[Groups(['event:read'])]
        public readonly array $payload,
        #[Groups(['event:read'])]
        public readonly Uuid $id,
        #[Groups(['event:read'])]
        public readonly int $version = 1,
        #[Groups(['event:read'])]
        public readonly \DateTimeImmutable $occurredOn = new \DateTimeImmutable()
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            Uuid::fromString($data['aggregateId']),
            $data['eventType'],
            $data['payload'],
            Uuid::fromString($data['id']),
            $data['version'],
            new \DateTimeImmutable($data['occurredOn'])
        );
    }

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