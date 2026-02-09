---
name: doctrine-strict
description: Strict Doctrine usage rules. Explicit queries over magic methods.
---

# Doctrine Strict Architecture

## Core Mandate: NO Magic Methods

The use of Doctrine's "magic" or "convenience" retrieval methods is **strictly forbidden** in this architecture to ensure performance, clarity, and maintainability.

### 🚫 Forbidden Methods

- `find($id)`
- `findAll()`
- `findBy(['field' => $value])`
- `findOneBy(['field' => $value])`

### ✅ Required Approach: QueryBuilder / DBAL

All data retrieval must be done via:

1.  **Explicit QueryBuilder**: Inside a Repository class.
2.  **Native SQL (DBAL)**: via `execute()` or `query()` in `ReadEntityManager` for high-performance reads.

## Why?

1.  **Performance**: Magic methods often select _all_ fields (`SELECT *`), loading unnecessary data and hydrating heavy objects.
2.  **Clarity**: Explicit queries (`findActiveUsersWithOrders`) document the intent better than generic calls.
3.  **Control**: You control the Joins, the Selects, and the Hydration mode (`getArrayResult`).

## Implementation Guide

### 1. Refactoring `find($id)`

**Bad:**

```php
$user = $userRepository->find($id);
```

**Good (Repository):**

```php
public function getById(Uuid $id): UserEntity
{
    $user = $this->createQueryBuilder('u')
        ->where('u.id = :id')
        ->setParameter('id', $id)
        ->getQuery()
        ->getOneOrNullResult();

    if (!$user) {
        throw new EntityNotFoundException('User not found');
    }
    return $user;
}
```

### 2. Refactoring `findBy()`

**Bad:**

```php
$users = $userRepository->findBy(['isActive' => true], ['createdAt' => 'DESC']);
```

**Good (Repository):**

```php
public function findActiveUsers(): array
{
    return $this->createQueryBuilder('u')
        ->select('u.id', 'u.name') // Select specific fields!
        ->where('u.isActive = true')
        ->orderBy('u.createdAt', 'DESC')
        ->getQuery()
        ->getArrayResult(); // Performance optimization
}
```
