# PHP Readonly Policy

Last updated: 2026-02-24

## Goal

Set a default for immutable design in PHP code generation and refactoring.

## Default Rule

Use `final readonly class` whenever a class:

1. has constructor-only state, and
2. has no state mutation after construction.

## Applies To

- Value Objects
- Command messages and immutable DTO-like internal messages
- Stateless/immutable services with constructor-injected dependencies
- Event store records modeled as immutable data structures

## Exceptions (Keep Mutable / Non-Readonly)

- Doctrine entities (`*Entity`) and persistence models managed by ORM/hydration
- API boundary DTOs that serializers/framework populate mutably
- Symfony controllers and console commands extending framework base classes
- Messaging transports/factories using lazy clients, connection pools or internal mutable caches
- Any class requiring post-construction mutation by design

## Refactoring Checklist

1. Confirm no setters or mutating methods exist.
2. Confirm properties are assigned only in constructor.
3. Convert declaration to `final readonly class`.
4. Run static analysis and tests:
    - `make phpstan`
    - `./vendor/bin/phpunit`

## Notes

- `readonly` is a design contract, not just syntax sugar: once applied, any hidden mutation becomes a runtime error.
- If framework integration later needs mutability, revert class-level `readonly` and document why.
