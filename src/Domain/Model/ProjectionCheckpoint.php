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
#[ORM\Table(name: 'projection_checkpoints')]
#[ApiResource(
    operations: [
        new Get(uriTemplate: '/checkpoints/{projectionName}'),
        new GetCollection(uriTemplate: '/checkpoints')
    ],
    normalizationContext: ['groups' => ['checkpoint:read']]
)]
class ProjectionCheckpoint
{
    #[ORM\Id]
    #[ORM\Column(length: 100)]
    #[Groups(['checkpoint:read'])]
    public private(set) string $projectionName;

    #[ORM\Column(type: 'uuid', nullable: true)]
    #[Groups(['checkpoint:read'])]
    public private(set) ?Uuid $lastEventId;

    #[ORM\Column]
    #[Groups(['checkpoint:read'])]
    public private(set) \DateTimeImmutable $updatedAt;

    public function __construct(string $projectionName)
    {
        $this->projectionName = $projectionName;
        $this->lastEventId = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function update(Uuid $eventId): void
    {
        $this->lastEventId = $eventId;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
