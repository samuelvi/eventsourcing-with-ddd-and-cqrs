<?php

declare(strict_types=1);

namespace App\Domain\Model;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Infrastructure\ApiPlatform\Provider\BookingProvider;
use App\Domain\Shared\NamedConstructorTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'bookings')]
#[ApiResource(
    operations: [
        new Get(uriTemplate: '/bookings/{id}', provider: BookingProvider::class),
        new GetCollection(uriTemplate: '/bookings', provider: BookingProvider::class, paginationEnabled: false, order: ['createdAt' => 'DESC'])
    ],
    normalizationContext: ['groups' => ['booking:read']]
)]
#[ApiFilter(OrderFilter::class, properties: ['createdAt'])]
class BookingEntity
{
    use NamedConstructorTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['booking:read'])]
    public private(set) Uuid $id;

    #[ORM\Column]
    #[Groups(['booking:read'])]
    public \DateTimeImmutable $createdAt;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['booking:read'])]
    public array $data;

    /**
     * @param array<string, mixed> $data
     */
    protected function __construct(Uuid $id, array $data, \DateTimeImmutable $createdAt)
    {
        $this->id = $id;
        $this->data = $data;
        $this->createdAt = $createdAt;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function hydrate(Uuid $id, array $data, \DateTimeImmutable $createdAt): self
    {
        return new self($id, $data, $createdAt);
    }
}