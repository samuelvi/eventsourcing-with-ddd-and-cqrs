# Value Objects Policy

Last updated: 2026-02-23

## Purpose

Define where Value Objects (VO) are mandatory and where primitives are intentionally kept for compatibility and operational stability.

## Scope

Applies to backend code under:

- `src/App/Domain`
- `src/App/Application`
- `src/App/Infrastructure`

## Rules

1. Domain core (`Model`, domain services, invariants) MUST use VO for business concepts.
2. Application handlers SHOULD convert boundary primitives to VO as early as possible.
3. API DTOs, HTTP payloads and serializer-bound events MAY stay primitive to preserve public contract stability.
4. Event Store payload format remains primitive unless an explicit event-versioning/upcaster strategy is implemented.
5. Read-model entities (`*Entity`) and DBAL row arrays MAY stay primitive; mapping to VO happens before entering domain logic.

## Current Coverage

VO-first coverage is implemented for:

- User flow:
    - `User` aggregate
    - create/update/delete handlers and API processors
- Booking flow:
    - `Booking` aggregate
    - submit/quote generation path
- Product flow:
    - `Product` aggregate
    - product creation and projection validation

Core VO introduced:

- `Email`, `PersonName`, `Address`
- `UuidString`
- `PositiveInt`, `NonNegativeAmount`
- `Currency`, `Money`
- `ProductName`, `ProductType`

## Intentional Primitive Boundaries

The following stay primitive by design:

1. Domain event payload fields (`string/int/float`) for serializer compatibility.
2. API Platform DTOs/request payloads.
3. Read-side Doctrine entities and DBAL repositories.

## Migration Strategy for Remaining Areas

1. Keep boundary contracts stable.
2. Add VO mapping at entrypoints.
3. Move invariants to VO/domain only.
4. If events are ever typed with VO directly, introduce event versioning + upcasters before rollout.
