# Gemini Project Context: n8n POC

## Project Skills & Agents
This project uses specialized engineering guides located in the `.skills/` directory. Gemini MUST consult, respect, and apply **ALL** rules found in `.skills/*.md` automatically before performing any task.

Current guides include:
- **Clean Code**: Core coding principles, best practices, and refactoring patterns.
- **PHP Expert**: Modern PHP 8.4+ standards (Strict Types, Property Hooks, PER Style).
- **Symfony Expert**: Symfony 6.4/7.0 best practices (Attributes, Autowiring).
- **API Platform Expert**: Standard API Platform 3.4+ usage.
- **Repository Pattern**: Data access best practices with Doctrine QueryBuilder.
- **CQRS Pattern**: Command Query Responsibility Segregation guidelines.
- **Doctrine Performance**: Optimization patterns (getArrayResult, eager loading).
- **Event Sourcing**: Pattern for capturing state changes as events.
- **N+1 Pagination**: High-performance pagination strategy.
- **Skill Creator**: Guide for creating new skills.
- **n8n Expert**: Workflow optimization and robustness.

## Development Environment
- **Backend**: Symfony 6.4 + API Platform (located in root `/`).
- **Automation**: n8n (Dockerized).
- **Database**: PostgreSQL 16.
- **Tools**: Adminer for DB management.
- **Commands**: Use `make` for common tasks (`make dev-up`, `make setup-api`, etc.).

## Core Mandates
- **Follow Skills First:** Before generating any code, consult the relevant expert skills.
- **Standard Architecture:** Use standard Symfony/API Platform patterns.
- **Performance First:** Apply `doctrine-performance` principles (bypass identity map for reads, select specific fields).
- **Operational Safety:**
  - **Avoid Blocking Commands:** NEVER execute commands that hang or wait for infinite input.
  - **Self-Correction:** Analyze failures immediately.
  - **Verification:** Use `curl` or one-off checks to verify health.
