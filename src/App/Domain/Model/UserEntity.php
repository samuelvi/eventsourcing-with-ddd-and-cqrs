<?php

declare(strict_types=1);

namespace App\Domain\Model;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Application\Dto\CreateUserDto;
use App\Infrastructure\ApiPlatform\State\CreateUserProcessor;
use App\Infrastructure\ApiPlatform\Provider\UserProvider;
use App\Domain\Shared\NamedConstructorTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ApiResource(
    shortName: 'User',
    operations: [
        new Get(uriTemplate: '/users/{id}', provider: UserProvider::class),
        new GetCollection(uriTemplate: '/users', paginationEnabled: false, provider: UserProvider::class),
        new Post(
            uriTemplate: '/users',
            input: CreateUserDto::class,
            processor: CreateUserProcessor::class,
            status: 202
        )
    ],
    normalizationContext: ['groups' => ['user:read']]
)]
class UserEntity
{
    use NamedConstructorTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['user:read'])]
    public private(set) Uuid $id;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['user:read'])]
    public string $email;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['user:read'])]
    public string $name;

    private function __construct(string $name, string $email, ?Uuid $id = null)
    {
        $this->id = $id ?? Uuid::v7();
        $this->name = $name;
        $this->email = $email;
    }

    public static function hydrate(string $name, string $email, Uuid $id): self
    {
        return new self($name, $email, $id);
    }
}
