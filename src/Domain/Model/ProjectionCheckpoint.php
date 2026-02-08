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
 * Plain Domain Object for ProjectionCheckpoint
 */
#[ApiResource(
    operations: [
        new Get(uriTemplate: '/checkpoints/{projectionName}', provider: MongoStoreProvider::class),
        new GetCollection(uriTemplate: '/checkpoints', provider: MongoStoreProvider::class)
    ],
    normalizationContext: ['groups' => ['checkpoint:read']]
)]
class ProjectionCheckpoint
{
    public function __construct(
        #[Groups(['checkpoint:read'])]
        public readonly string $projectionName,
        #[Groups(['checkpoint:read'])]
        public ?Uuid $lastEventId = null,
        #[Groups(['checkpoint:read'])]
        public \DateTimeImmutable $updatedAt = new \DateTimeImmutable()
    ) {}

    public function update(Uuid $eventId): void
    {
        $this->lastEventId = $eventId;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['projectionName'],
            $data['lastEventId'] ? Uuid::fromString($data['lastEventId']) : null,
            new \DateTimeImmutable($data['updatedAt'])
        );
    }

    public function toArray(): array
    {
        return [
            'projectionName' => $this->projectionName,
            'lastEventId' => $this->lastEventId?->toRfc4122(),
            'updatedAt' => $this->updatedAt->format(\DateTimeInterface::ATOM)
        ];
    }
}