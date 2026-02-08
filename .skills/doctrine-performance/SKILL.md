---
name: doctrine-performance
description: Doctrine performance optimization patterns.
---

# Doctrine Performance Optimization

## Key Principles

### 1. Bypass Identity Map with getArrayResult()
For read-only lists/APIs, use `getArrayResult()`. It is much faster as it skips hydration and Identity Map overhead.

```php
// ✅ Optimized
$results = $qb->getQuery()->getArrayResult();

// ❌ Slow (for large datasets)
$results = $qb->getQuery()->getResult();
```

### 2. Select Only Needed Fields
Avoid `select('u')` (all fields). Select specific fields.

```php
// ✅ Optimized
$qb->select('u.id', 'u.name');

// ❌ Slow
$qb->select('u');
```

### 3. Eager Loading (Joins)
Prevent N+1 problems by joining related entities in the main query.

```php
// ✅ Optimized
$qb->select('u', 'o')
   ->leftJoin('u.orders', 'o');
```

### 4. Existence Checks (LIMIT 1)
Use `LIMIT 1` logic instead of `COUNT(*)`.

```php
// ✅ Optimized
public function exists(string $email): bool
{
    return (bool) $this->createQueryBuilder('u')
        ->select('1')
        ->where('u.email = :email')
        ->setParameter('email', $email)
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
}
```

### 5. Zero-Query Optimization
Check inputs before querying.

```php
public function findByIds(array $ids): array
{
    if (empty($ids)) {
        return []; // ✅ Zero DB calls
    }
    // ... query
}
```
