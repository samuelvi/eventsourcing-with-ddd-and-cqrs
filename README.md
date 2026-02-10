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

## 🏗️ Architecture: Enterprise-Grade Hybrid

- **DDD (Domain-Driven Design):** Strict separation of layers (Domain, Application, Infrastructure).
- **Source of Truth (Write Side):** **MongoDB** storing immutable `StoredEvent` objects.
- **Read Models (Read Side):** **PostgreSQL** storing optimized SQL tables for the UI.
- **Concurrency Control**: **Optimistic Locking** enforced via MongoDB Unique Indexes (`aggregateId` + `version`).
- **CQRS:** Physical separation of Read (DBAL/SQL) and Write (ORM) responsibilities.
- **Checkpoints & Snapshots:** Managed in MongoDB to decouple technical state from business data.
- **Automatic Snapshots:** Configurable state capture to speed up historical replay.

## 🛠️ Advanced Commands

### 🔍 Quality Analysis

```bash
make phpstan    # Run PHPStan Level 9
npm run lint    # Run ESLint 9
npm run format  # Format with Prettier
```

### 🔄 Rebuilding Projections (Disaster Recovery)

If Postgres data is lost, it can be fully restored by replaying history from MongoDB:

```bash
docker compose exec symfony-api bin/console app:projections:rebuild
```

### 🧪 Running Tests

```bash
make test
```
