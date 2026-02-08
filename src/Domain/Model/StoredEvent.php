<?php

declare(strict_types=1);

namespace App\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'event_store')]
class StoredEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public private(set) Uuid $id;

    #[ORM\Column(type: 'uuid')]
    public private(set) Uuid $aggregateId;

    #[ORM\Column(length: 255)]
    public private(set) string $eventType;

    #[ORM\Column(type: 'json')]
    public private(set) array $payload;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $occurredOn;

    public function __construct(Uuid $aggregateId, string $eventType, array $payload, ?Uuid $id = null)
    {
        $this->id = $id ?? Uuid::v7();
        $this->aggregateId = $aggregateId;
        $this->eventType = $eventType;
        $this->payload = $payload;
        $this->occurredOn = new \DateTimeImmutable();
    }
}
