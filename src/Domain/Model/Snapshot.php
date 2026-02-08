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
#[ORM\Table(name: 'snapshots')]
#[ApiResource(
    operations: [
        new Get(uriTemplate: '/snapshots/{id}'),
        new GetCollection(uriTemplate: '/snapshots')
    ],
    normalizationContext: ['groups' => ['snapshot:read']]
)]
class Snapshot
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['snapshot:read'])]
    public private(set) Uuid $id;

    #[ORM\Column(type: 'uuid')]
    #[Groups(['snapshot:read'])]
    public private(set) Uuid $aggregateId;

    #[ORM\Column(type: 'integer')]
    #[Groups(['snapshot:read'])]
    public private(set) int $version; // Last event version/index included in this snapshot

    #[ORM\Column(type: 'json')]
    #[Groups(['snapshot:read'])]
    public private(set) array $state;

    #[ORM\Column]
    #[Groups(['snapshot:read'])]
    public private(set) \DateTimeImmutable $createdAt;

    public function __construct(Uuid $aggregateId, int $version, array $state)
    {
        $this->id = Uuid::v7();
        $this->aggregateId = $aggregateId;
        $this->version = $version;
        $this->state = $state;
        $this->createdAt = new \DateTimeImmutable();
    }
}
