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
