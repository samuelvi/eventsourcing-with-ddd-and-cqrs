<?php

declare(strict_types=1);

namespace App\Domain\Model;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'event_store')]
#[ApiResource(
    shortName: 'EventStore',
    operations: [
        new Get(uriTemplate: '/event-store/{id}'),
        new GetCollection(uriTemplate: '/event-store')
    ],
    normalizationContext: ['groups' => ['event:read']]
)]
class StoredEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['event:read'])]
    public private(set) Uuid $id;

    #[ORM\Column(type: 'uuid')]
    #[Groups(['event:read'])]
    public private(set) Uuid $aggregateId;

    #[ORM\Column(length: 255)]
    #[Groups(['event:read'])]
    public private(set) string $eventType;

    #[ORM\Column(type: 'json')]
    #[Groups(['event:read'])]
    public private(set) array $payload;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    #[Groups(['event:read'])]
    public private(set) int $version;

    #[ORM\Column]
    #[Groups(['event:read'])]
    public private(set) \DateTimeImmutable $occurredOn;

    public function __construct(Uuid $aggregateId, string $eventType, array $payload, ?Uuid $id = null, int $version = 1)
    {
        $this->id = $id ?? Uuid::v7();
        $this->aggregateId = $aggregateId;
        $this->eventType = $eventType;
        $this->payload = $payload;
        $this->version = $version;
        $this->occurredOn = new \DateTimeImmutable();
    }
}
