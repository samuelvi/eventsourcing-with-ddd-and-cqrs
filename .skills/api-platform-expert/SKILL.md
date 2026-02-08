---
name: api-platform-expert
description: Best practices for API Platform 3.2+ development. Use when creating or modifying API resources, operations, and serialization groups.
---

# API Platform Expert

Follow standard API Platform 3.2+ best practices.

## Core Principles

- **Resources:** Map Entities directly using `#[ApiResource]` when simple. Use DTOs and `DataTransformer` or `StateProvider/Processor` for complex logic.
- **Operations:** Define operations explicitly (`#[Get]`, `#[Post]`, `#[GetCollection]`).
- **Serialization:** Use Serialization Groups (`#[Groups]`) to control output/input fields.
- **Filtering:** Use `#[ApiFilter]` for search/sorting.

## Configuration

- **State Providers/Processors:** Prefer creating custom Providers/Processors over Controller logic for complex business rules.
- **Validation:** Use Symfony Validator constraints (`#[Assert\NotBlank]`) on Entity properties.

## Example Resource

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['user:read']]),
        new GetCollection(normalizationContext: ['groups' => ['user:read']]),
        new Post(denormalizationContext: ['groups' => ['user:write']]),
    ]
)]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['user:read', 'user:write'])]
    private ?string $email = null;

    // ... getters/setters
}
```
