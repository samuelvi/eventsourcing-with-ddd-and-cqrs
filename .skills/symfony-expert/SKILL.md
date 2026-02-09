---
name: symfony-expert
description: Best practices for Symfony 6.4/7.0 development. Focus on Attributes, Property Hooks, and Autowiring.
---

# Symfony Expert

## Core Principles

- **Configuration:** Use `.env` and `yaml` (services).
- **Attributes:** Use PHP Attributes (`#[Route]`, `#[Entity]`) for all mapping.
- **No Getters/Setters:** Use PHP 8.4 Property Hooks or `public` properties.
- **Dependency Injection:** Constructor Injection only.

## Architecture

- **Controllers:** Thin. Logic in Services/Handlers.
- **Entities:**
    - Use `#[ORM\Entity]` attributes.
    - Use `public` properties with Hooks for validation/logic.
    - Use `public private(set)` for IDs or read-only fields.

## Example Entity (PHP 8.4)

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column(length: 255)]
    public string $name {
        set => ucfirst($value); // Example hook
    }

    #[ORM\Column(length: 255)]
    public string $email;
}
```
