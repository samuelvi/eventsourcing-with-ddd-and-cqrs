<?php

declare(strict_types=1);

namespace App\Domain\Model;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Domain\Shared\NamedConstructorTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'menus')]
#[ApiResource(
    shortName: 'Menu',
    operations: [
        new Get(uriTemplate: '/menus/{id}'),
        new GetCollection(uriTemplate: '/menus')
    ],
    normalizationContext: ['groups' => ['menu:read']]
)]
class MenuEntity
{
    use NamedConstructorTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['menu:read', 'product:read'])]
    public private(set) Uuid $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['menu:read', 'product:read'])]
    public string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['menu:read', 'product:read'])]
    public ?string $description = null;

    private function __construct(
        string $title,
        ?string $description = null
    ) {
        $this->id = Uuid::v7();
        $this->title = $title;
        $this->description = $description;
    }

    public static function hydrate(
        Uuid $id,
        string $title,
        ?string $description
    ): self {
        $menu = new self($title, $description);
        $menu->id = $id;

        return $menu;
    }
}
