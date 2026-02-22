---
name: php-expert
description: Modern PHP 8.4+ best practices. Mandatory usage of Property Hooks, strict types, and PER Coding Style.
---

# PHP Expert (PHP 8.4+)

Adhere to modern PHP 8.4+ standards.

## Core Standards

- **Strict Types:** ALWAYS start files with `<?php` followed by a blank line and `declare(strict_types=1);`.
- **Property Hooks:** Use PHP 8.4 Property Hooks `get; set;` instead of traditional getters/setters.
- **PER Coding Style:** Follow the modern PER (PHP Evolved Recommendations) style guide (successor to PSR-12).

## Industry Standards (PSR)

- **PSR-1 / PSR-12:** Basic coding standard and formatting baseline.
- **PSR-4:** Autoloading (namespace-path mapping).
- **PSR-3:** Logging contracts (`LoggerInterface`).
- **PSR-7:** HTTP message interfaces (when interacting with middleware stacks).
- **PSR-11:** Container interface (dependency retrieval boundaries).
- **PSR-14:** Event dispatcher contracts for decoupled event flow.

Apply the PSR that matches the concern being modified; do not force unrelated PSRs into the change.

## Modern Features

- **Property Hooks:**
    ```php
    public string $email {
        set {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Invalid email');
            }
            $this->email = $value;
        }
    }
    ```
- **Constructor Property Promotion:** Use for DTOs/VOs.
- **Asymmetric Visibility:** `public private(set) string $id;`
- **Match Expression:** Use `match` instead of `switch`.

## Best Practices

- **No Getters/Setters:** Do not generate boilerplate `getFoo()`/`setFoo()` methods. Use public properties with hooks or asymmetric visibility.
- **Composition:** Prefer composition over inheritance.
- **Immutability:** Use `readonly` classes for DTOs.
- **Early Returns:** Prefer guard clauses and early returns to reduce nesting and keep flow linear.
- **Null Coalescing First:** For simple fallback/default values, prefer `??` over verbose ternaries.
    - Prefer: `$address = $row['address'] ?? null;`
    - Avoid: `isset($row['address']) && is_string($row['address']) ? $row['address'] : null`

## Project Companion Skills

- If the task touches DDD/Event Sourcing code with static analysis constraints, also apply `phpstan-ddd-guardrails`.
