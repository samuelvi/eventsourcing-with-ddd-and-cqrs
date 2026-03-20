<?php

declare(strict_types=1);

namespace App\Domain\Model;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Domain\Shared\NamedConstructorTrait;
use App\Quotes\Infrastructure\ApiPlatform\Provider\QuoteProvider;
use App\Quotes\Infrastructure\ApiPlatform\State\QuoteStatusProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'quotes')]
#[ApiResource(
    shortName: 'Quote',
    operations: [
        new Get(uriTemplate: '/quotes/{id}', provider: QuoteProvider::class),
        new GetCollection(uriTemplate: '/quotes', provider: QuoteProvider::class),
        new Patch(
            uriTemplate: '/quotes/{id}',
            normalizationContext: ['groups' => ['quote:read']],
            denormalizationContext: ['groups' => ['quote:write']],
            processor: QuoteStatusProcessor::class
        )
    ],
    normalizationContext: ['groups' => ['quote:read']]
)]
#[ApiFilter(SearchFilter::class, properties: ['bookingId' => 'exact', 'supplierId' => 'exact'])]
class QuoteEntity
{
    use NamedConstructorTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['quote:read'])]
    public private(set) Uuid $id;

    #[ORM\Column(type: 'uuid')]
    #[Groups(['quote:read'])]
    public private(set) Uuid $bookingId;

    #[ORM\Column(type: 'uuid')]
    #[Groups(['quote:read'])]
    public private(set) Uuid $supplierId;

    #[ORM\Column(type: 'uuid')]
    #[Groups(['quote:read'])]
    public private(set) Uuid $productId;

    public const STATUS_PENDING = 'pending';
    public const STATUS_QUOTE_SENT = 'quote_sent';
    public const STATUS_QUOTED = self::STATUS_QUOTE_SENT;
    public const STATUS_NOT_RESPONDED = 'not_responded';
    public const STATUS_DISCARDED = 'discarded';
    public const STATUS_EXPIRED = 'expired';

    #[ORM\Column(length: 50)]
    #[Groups(['quote:read', 'quote:write'])]
    public string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'float')]
    #[Groups(['quote:read'])]
    public float $price;

    #[ORM\Column(type: 'string', length: 2048, nullable: true)]
    #[Groups(['quote:read'])]
    public ?string $n8nCallbackUrl = null;

    #[ORM\Column]
    #[Groups(['quote:read'])]
    public \DateTimeImmutable $createdAt;

    private function __construct(
        Uuid $id,
        Uuid $bookingId,
        Uuid $supplierId,
        Uuid $productId,
        float $price,
        \DateTimeImmutable $createdAt,
        string $status = self::STATUS_PENDING,
        ?string $n8nCallbackUrl = null,
    ) {
        $this->id = $id;
        $this->bookingId = $bookingId;
        $this->supplierId = $supplierId;
        $this->productId = $productId;
        $this->price = $price;
        $this->createdAt = $createdAt;
        $this->status = $status;
        $this->n8nCallbackUrl = $n8nCallbackUrl;
    }

    public static function hydrate(
        Uuid $id,
        Uuid $bookingId,
        Uuid $supplierId,
        Uuid $productId,
        float $price,
        \DateTimeImmutable $createdAt,
        string $status = self::STATUS_PENDING,
        ?string $n8nCallbackUrl = null,
    ): self {
        return new self($id, $bookingId, $supplierId, $productId, $price, $createdAt, $status, $n8nCallbackUrl);
    }
}
