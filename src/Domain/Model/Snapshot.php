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
 * Plain Domain Object for Snapshot
 */
#[ApiResource(
    operations: [
        new Get(uriTemplate: '/snapshots/{id}', provider: MongoStoreProvider::class),
        new GetCollection(uriTemplate: '/snapshots', provider: MongoStoreProvider::class)
    ],
    normalizationContext: ['groups' => ['snapshot:read']]
)]
class Snapshot
{
    public function __construct(
        #[Groups(['snapshot:read'])]
        public readonly Uuid $id,
        #[Groups(['snapshot:read'])]
        public readonly Uuid $aggregateId,
        #[Groups(['snapshot:read'])]
        public readonly int $version,
        #[Groups(['snapshot:read'])]
        public readonly array $state,
        #[Groups(['snapshot:read'])]
        public readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable()
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            Uuid::fromString($data['id']),
            Uuid::fromString($data['aggregateId']),
            $data['version'],
            $data['state'],
            new \DateTimeImmutable($data['createdAt'])
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'aggregateId' => $this->aggregateId->toRfc4122(),
            'version' => $this->version,
            'state' => $this->state,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM)
        ];
    }
}