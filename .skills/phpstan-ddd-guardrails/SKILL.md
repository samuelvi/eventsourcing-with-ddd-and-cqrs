---
name: phpstan-ddd-guardrails
description: Guardrails for implementing PHP DDD/Event Sourcing code that must pass strict PHPStan; use for new features and bugfixes touching aggregates, snapshots, projections, repositories, or providers.
---

# PHPStan DDD Guardrails

Apply these rules before and during implementation.

## Mandatory Workflow

1. Implement with explicit types first (`string|int|float|array<string,mixed>` as needed).
2. If reading external/mixed payloads, normalize immediately with `TypeAssert`.
3. Model generic repositories with template annotations (`@template`, `@implements`, `@extends`).
4. Run `make phpstan` before claiming completion.

## Rules That Prevent Common Failures

- Do not assign raw snapshot/event-store values directly to typed properties.
- Prefer fixing contracts over local casts.
- Use `static` return in aggregate reconstitution APIs when subclass fidelity is required.
- Keep constructor signatures consistent in aggregate hierarchies (`@phpstan-consistent-constructor`).
- Remove injected dependencies/properties that are never read.
- When serializing events, convert decoded payloads to typed arrays before use.

## Event Sourcing Specifics

- Aggregate contracts:
    - `reconstituteFromHistory(...): static`
    - `reconstituteFromSnapshot(...): static`
- Snapshot hydration:
    - `TypeAssert::string|int|float|array` for every required field.
- Generic repository:
    - `@template T of AggregateRootInterface`
    - `@implements EventStoreRepositoryInterface<T>`
    - Constructor receives `class-string<T>`.

## Anti-Patterns

- `/** @var T $x */` used as first option to silence PHPStan.
- Passing `mixed` from decoded JSON straight into domain constructors/hydrators.
- Leaving comments like "for now" around typing gaps in core repository code.

## Definition of Done

- `make phpstan` exits `0`.
- No new PHPStan baseline suppressions.
- No unresolved `mixed` assignments in changed files.
