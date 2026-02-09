<?php

declare(strict_types=1);

namespace App\Domain\Model;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Infrastructure\ApiPlatform\Provider\SupplierProvider;
use App\Domain\Shared\NamedConstructorTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'suppliers')]
#[ApiResource(
    shortName: 'Supplier',
    operations: [
        new Get(uriTemplate: '/suppliers/{id}'),
        new GetCollection(uriTemplate: '/suppliers', provider: SupplierProvider::class),
        new Post(uriTemplate: '/suppliers')
    ],
    normalizationContext: ['groups' => ['supplier:read']],
    denormalizationContext: ['groups' => ['supplier:write']]
)]
class SupplierEntity
{
    use NamedConstructorTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['supplier:read'])]
    public private(set) Uuid $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['supplier:read', 'supplier:write'])]
    public string $name;

    #[ORM\Column]
    #[Groups(['supplier:read', 'supplier:write'])]
    public bool $isActive = true;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 10)]
    #[Groups(['supplier:read', 'supplier:write'])]
    public float $rating = 0.0;

    /** @var Collection<int, ProductEntity> */
    #[ORM\OneToMany(mappedBy: 'supplier', targetEntity: ProductEntity::class)]
    #[Groups(['supplier:read'])]
    public private(set) Collection $products;

    protected function __construct(string $name, ?Uuid $id = null, bool $isActive = true, float $rating = 0.0)
    {
        $this->id = $id ?? Uuid::v7();
        $this->name = $name;
        $this->isActive = $isActive;
        $this->rating = $rating;
        $this->products = new ArrayCollection();
    }

    public static function hydrate(Uuid $id, string $name, bool $isActive, float $rating): self
    {
        return new self($name, $id, $isActive, $rating);
    }
}