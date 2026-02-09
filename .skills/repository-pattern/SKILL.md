---
name: repository-pattern
description: Repository Pattern implementation with Doctrine QueryBuilder. Encapsulates data access logic and enforces consistent query patterns.
---

# Repository Pattern

## Overview

Encapsulates data access logic, preventing direct `EntityManager` usage in business logic.

## Key Principles

1.  **No Magic Methods:** Do not use `find()`, `findBy()`, `findOneBy()`. Use explicit QueryBuilder methods.
2.  **Return Types:**
    - **Read-Only:** Return arrays (`getArrayResult()`) for performance and to bypass the Identity Map.
    - **Write/Update:** Return Entities only when modification is needed.
3.  **Explicit Naming:** Method names should describe the query (e.g., `findForInvoicePrefill`, `findAllForList`).

## Implementation Example

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @return array<array{id: int, name: string, email: string}>
     */
    public function findAllForList(): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.id', 'u.name', 'u.email')
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getArrayResult(); // ✅ Array result for performance
    }

    public function findOneActiveByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->andWhere('u.isActive = true')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
```

## Best Practices

- **Inject Repository:** Inject specific repositories into Services/Handlers, never `EntityManagerInterface`.
- **Select Specific Fields:** Only select fields needed for the specific view/logic.
- **Use Constants:** Use Entity constants for status checks.
