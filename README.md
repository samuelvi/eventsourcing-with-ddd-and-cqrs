# Event Sourcing & n8n POC

This is a high-performance Proof of Concept (POC) for an event-sourced booking system using **Symfony 7.2**, **PHP 8.4**, **API Platform 3.4**, **React 18 (Vite)**, and **n8n**.

## 🛡️ Technical Excellence & Quality

This project is built with a "Zero-Defect" mentality, enforcing the strictest industry standards:

- **Backend Robustness**: Enforced **PHPStan Level 9** (Max) across the entire codebase.
- **Type Safety**: Implementation of a custom `TypeAssert` utility to bridge the gap between non-typed storage (DBAL/Mongo) and strict Domain Models.
- **Modern Frontend**: React with **TypeScript Strict**, **TanStack Query** for robust data fetching, and **ESLint 9** (Flat Config).
- **CI/CD**: Automated **GitHub Actions** pipeline verifying types, linting, formatting (Prettier), and tests on every push.
- **Zero Deprecations**: Optimized for future-proof compatibility with Symfony 8.

## 🚀 Quick Start

To initialize the entire system from scratch (containers, database, dependencies, and test data):

```bash
make init
```

## 🌐 Service URLs

### 🖥️ Frontend

- **TED Demo Mode:** [http://localhost:8080/demo](http://localhost:8080/demo) (Recommended for presentation)
- **Main Entry Point:** [http://localhost:8080/](http://localhost:8080/)
- **Data Explorer:** [http://localhost:8080/explorer](http://localhost:8080/explorer)

### 🔌 API & Documentation

- **Swagger UI (API Docs):** [http://localhost:8080/docs](http://localhost:8080/docs)

#### Core Endpoints

- **Event Store (Mongo):** `GET http://localhost:8080/api/event-store`
- **Projections (Postgres):** `GET http://localhost:8080/api/users`, `GET http://localhost:8080/api/bookings`
- **Checkpoints (Mongo):** `GET http://localhost:8080/api/checkpoints`

### ⚙️ Automation & Tools

- **n8n Workflow Tool:** [http://localhost:5678/](http://localhost:5678/)
- **Adminer (Postgres Mgmt):** [http://localhost:8081/](http://localhost:8081/)
- **Mongo Express (Mongo Mgmt):** [http://localhost:8082/](http://localhost:8082/)

## 🏗️ Architecture: Enterprise-Grade Pure Event Sourcing

- **Clean Structure**: Core application logic isolated in `src/App/`, following a strict Layered Architecture (Application, Domain, Infrastructure).
- **Pure Aggregates**: Domain models (`User`, `Product`, `Booking`) are pure PHP objects, completely decoupled from Doctrine or any infrastructure.
- **Source of Truth (Write Side)**: **MongoDB** storing immutable events with **Optimistic Concurrency Control** (Aggregate Versioning).
- **Read Models (Read Side)**: **PostgreSQL** providing optimized projections for high-speed queries.
- **Deterministic Identity**: Uses **UUID v5** for users to ensure autonomous, collision-free identity across the system without read-leaks.
- **Isolated Testing**: Dedicated test infrastructure in `src/Test/` and a mirrored Docker environment for E2E tests.

## 🛠️ Advanced Commands

### 🔍 Quality & Maintenance

```bash
make phpstan    # Run PHPStan Level 9 (Strictest)
npm run lint    # Run ESLint 9
make dev-clean  # Full wipe of development data
```

### 🔄 System Recovery & Reset

```bash
make load-fixtures # Full reset: Wipe SQL + Mongo and reload catalogs
docker compose exec symfony-api bin/console app:projections:rebuild # Disaster recovery
```

### 🧪 Professional Testing Suite

The project features a full BDD E2E suite using **Playwright** and **Gherkin**:

```bash
make test       # Run isolated functional tests (PHPUnit)
make test-e2e   # Run Gherkin scenarios (Headless)
make test-e2e-ui # Run Gherkin scenarios (UI Mode / Interactive)
```
