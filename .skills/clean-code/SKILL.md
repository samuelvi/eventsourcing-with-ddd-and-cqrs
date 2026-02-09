---
name: clean-code
description: Core coding standards and principles. Includes specific PHP Best Practices, Refactoring patterns, and Clean Code guidelines.
---

# Clean Code & Best Practices

## PHP Best Practices (Core)

### Language and Comments

- **English Only:** All code, variable names, function names, class names, and **comments** must be in English.
- **No "Spanglish":** Avoid mixing languages.

```php
// ❌ BAD
// Calculamos el total
$total = $precio + $impuesto;

// ✅ GOOD
// Calculate total
$total = $price + $tax;
```

### Null and Empty Checks

- **Use `empty()`:** Prefer `empty()` over checking for `null` or `""` explicitly when you want to catch both.
- **Be explicit when necessary:** Only check for `=== null` if `0` or `false` are valid values.

```php
// ❌ BAD
if ($email === null || $email === '') { ... }

// ✅ GOOD
if (empty($email)) { ... }
```

### Type Declarations

- **Strict Types:** Always use `declare(strict_types=1);` at the top of PHP files.
- **Type Hinting:** Always type hint arguments and return values.

### Dependency Injection

- **Constructor Injection:** Prefer constructor injection over setter or property injection.
- **Interfaces:** Type hint against interfaces, not concrete classes.

### Performance Mindset (IO Operations)

- **Early Returns:** Avoid unnecessary "round-trips" by validating inputs that would result in empty/no-op operations.

```php
// ✅ GOOD: Avoid DB call if we know the answer
public function findUsers(array $ids): array
{
    if (empty($ids)) {
        return [];
    }
    // ... query DB
}
```

## Clean Code Principles (General)

### Newspaper Metaphor

Code should read like a newspaper article: from general to specific. Public API first, private helpers last.

### Early Returns

Avoid deep nesting. Return as soon as invalid conditions are met.

```php
// ✅ GOOD
public function process(?Data $data): void
{
    if ($data === null) return;
    if (!$data->isValid()) return;

    $this->doWork($data);
}
```

### Single Responsibility

Each class/function should do one thing.

### Descriptive Naming

Avoid abbreviations. Names should reveal intent.

### Small Functions

Keep functions small (< 20 lines where possible).

## Refactoring Patterns (Martin Fowler)

### Tell, Don't Ask

Don't ask for data to make decisions. Tell the object what to do.

```php
// ❌ BAD
if ($user->getRole() === 'admin') {
    $user->setPermissions(['all']);
}

// ✅ GOOD
$user->grantAdminPermissions();
```

### Replace Magic Numbers/Strings

Use named constants.

```php
const STATUS_PENDING = 'pending';
if ($order->status === STATUS_PENDING) { ... }
```

## Architecture Mandates

### 1. No EntityManager in Controllers

**Strict Rule:** Controllers MUST NOT have any type of `EntityManager` (Doctrine, Read, Write, etc.) injected.

- **Why:** Controllers should only handle HTTP concerns. DB logic belongs in Repositories or Application Services.
- **Correct approach:** Inject a Repository for queries or an Application Service for complex logic.

### 2. Named Constructors

Use static factory methods instead of public constructors whenever possible to increase semantic clarity.

### 3. Explicit Queries

Follow the `doctrine-strict` skill: avoid magic methods like `find()` or `findBy()`. Use explicit QueryBuilder or DBAL calls in Repositories.
