<?php

declare(strict_types=1);

namespace App\Domain\Model;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Domain\Shared\NamedConstructorTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[ApiResource(
    shortName: 'Product',
    operations: [
        new Get(uriTemplate: '/products/{id}'),
        new GetCollection(uriTemplate: '/products'),
        new Post(uriTemplate: '/products')
    ],
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']]
)]
class ProductEntity
{
    use NamedConstructorTrait;

    final public const TYPE_MENU = 'menu';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['product:read', 'supplier:read'])]
    public private(set) Uuid $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['product:read', 'product:write', 'supplier:read'])]
    public string $name;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Groups(['product:read', 'product:write'])]
    public string $type = self::TYPE_MENU;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    #[Groups(['product:read', 'product:write'])]
    public float $price;

    #[ORM\ManyToOne(targetEntity: SupplierEntity::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['product:read', 'product:write'])]
    public SupplierEntity $supplier;

    #[ORM\Column(type: 'uuid', nullable: true)]
    #[Groups(['product:read'])]
    public private(set) ?Uuid $externalReferenceId = null;

    /**
     * Virtual property to hold the hydrated details (Menu, Ticket, etc.)
     * Not persisted in DB, filled by Provider/Factory.
     */
    #[Groups(['product:read'])]
    public ?object $details = null;

    protected function __construct(
        string $name,
        float $price,
        SupplierEntity $supplier,
        string $type = self::TYPE_MENU,
        ?Uuid $externalReferenceId = null
    ) {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->price = $price;
        $this->supplier = $supplier;
        $this->type = $type;
        $this->externalReferenceId = $externalReferenceId;
    }

    public function setDetails(?object $details): void
    {
        $this->details = $details;
    }
}
