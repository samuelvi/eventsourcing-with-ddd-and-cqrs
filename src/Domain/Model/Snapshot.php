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
 * Plain Domain Object for Snapshot
 */
#[ApiResource(
    operations: [
        new Get(uriTemplate: '/snapshots/{id}', provider: MongoStoreProvider::class),
        new GetCollection(uriTemplate: '/snapshots', provider: MongoStoreProvider::class, paginationEnabled: false)
    ],
    normalizationContext: ['groups' => ['snapshot:read']]
)]
class Snapshot
{
    private function __construct(
        #[Groups(['snapshot:read'])]
        public readonly Uuid $id,
        #[Groups(['snapshot:read'])]
        public readonly Uuid $aggregateId,
        #[Groups(['snapshot:read'])]
        public readonly int $version,
        /** @var array<string, mixed> */
        #[Groups(['snapshot:read'])]
        public readonly array $state,
        #[Groups(['snapshot:read'])]
        public readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable()
    ) {}

    /**
     * @param array<string, mixed> $state
     */
    public static function take(
        Uuid $aggregateId,
        int $version,
        array $state
    ): self {
        return new self(Uuid::v7(), $aggregateId, $version, $state);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Uuid::fromString(TypeAssert::string($data['id'])),
            Uuid::fromString(TypeAssert::string($data['aggregateId'])),
            TypeAssert::int($data['version']),
            TypeAssert::array($data['state']),
            new \DateTimeImmutable(TypeAssert::string($data['createdAt']))
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
            'version' => $this->version,
            'state' => $this->state,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM)
        ];
    }
}