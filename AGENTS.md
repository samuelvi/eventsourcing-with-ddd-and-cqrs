# AGENTS

Last updated: 2026-02-22

## Scope

Operational context for coding agents in this repository. Keep this file concise, stable, and oriented to invariants.

## Architecture Snapshot

- Backend: Symfony 7.4 + API Platform + DDD/CQRS/Event Sourcing.
- Frontend: React 18 + Vite (served through Symfony SPA route).
- Data stores:
    - MongoDB: event store, checkpoints, snapshots.
    - PostgreSQL (domain): read models.
    - PostgreSQL (messaging): Symfony Messenger queues.

## Invariants (Do Not Drift)

- Command buses:
    - `AsyncCommandBusInterface` is default async dispatch (`TransportNamesStamp(['async'])`).
    - `SyncCommandBusInterface` is explicit sync dispatch (`TransportNamesStamp(['sync'])`).
- Business policy:
    - Booking submission stays async (`BookingWizardController`).
    - User create/update/delete stays sync (API Platform processors for users).
- Messenger routing baseline:
    - `App\\Application\\Command\\* -> async`
    - `App\\Domain\\Event\\* -> async`
    - Sync is opt-in via sync bus stamp, not via global routing changes.

## Source Map (By Folder)

- Domain and app logic: `src/App/`
- HTTP controllers: `src/App/Infrastructure/Http/Controller/`
- API Platform processors/providers:
    - `src/App/Infrastructure/ApiPlatform/State/`
    - `src/App/Infrastructure/ApiPlatform/Provider/`
- Test-only helpers/controllers: `src/Test/`
- Config:
    - services: `config/services.yaml`
    - messenger: `config/packages/messenger.yaml`
    - messenger test overrides: `config/packages/test/messenger.yaml`

## Test Map (By Type)

- PHPUnit (unit/integration): `tests/`
- E2E (Playwright BDD): `tests/e2e/`
    - Features: `tests/e2e/features/`
    - Steps: `tests/e2e/steps/`
- E2E tags:
    - `@reset`: reset state before scenario via `/api/test/reset-db-empty`
    - `@no-reset`: preserve state across chained scenarios

## CI/Env Guardrails

- Messaging integration tests can run in environments without queue host resolution.
- Tests that require messaging transport must skip gracefully when transport DB is unavailable.
- Avoid hard-coupling unit test success to docker hostnames.

## Verification Commands

- Static analysis: `make phpstan`
- PHPUnit: `./vendor/bin/phpunit`
- Full dockerized suite: `make test-all`
- E2E only: `make test-e2e`

## Agent Startup Checklist

1. `git status --short`
2. Read `AGENTS.md`
3. Reconfirm invariants:
    - booking async
    - users sync
    - messenger command routing async by default
4. Before claiming completion on backend changes:
    - run `make phpstan`
    - run relevant PHPUnit subset (or full `./vendor/bin/phpunit` when feasible)
