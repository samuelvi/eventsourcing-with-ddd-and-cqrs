# Event Sourcing With DDD & CQRS

POC de reservas con Event Sourcing usando Symfony 7.2, PHP 8.4, API Platform 3.4, React 18 (Vite), PostgreSQL, MongoDB y n8n.

## Estado del proyecto

- Stack backend orientado a DDD + CQRS + Event Sourcing.
- Frontend SPA servido por Symfony con assets Vite.
- Entornos Docker separados para `dev` y `test`.

## Documentación técnica ampliada

Para explicación profunda de arquitectura, decisiones técnicas, flujo de reservas, snapshots, consistencia eventual y roadmap de sagas/orquestación:

- `TALK.md`

Este `README.md` está enfocado en operación diaria (arranque, tests y comandos).

## Quick start

Inicialización completa de desarrollo:

```bash
make dev-init
```

Alias compatible:

```bash
make init
```

## Flujo recomendado de inicialización

## 1) Desarrollo (DEV)

Comando principal:

```bash
make dev-init
```

Qué ejecuta (en orden):

1. `dev-down`
2. `dev-build`
3. `dev-up`
4. `dev-wait`
5. `setup-api`
6. `init-front`
7. `load-fixtures`

## 2) Pruebas (TEST)

Comando principal:

```bash
make test-init
```

Qué ejecuta (en orden):

1. `test-down`
2. `test-build`
3. `test-up` (incluye `build-front` para compilar assets Vite de producción)
4. `test-wait`
5. `setup-api-test`
6. `load-fixtures-test`

Nota importante:

- En el entorno `test` (http://localhost:9080) no existe Vite dev server.
- Por eso `test-up` compila frontend con `npx vite build` antes de levantar contenedores.

## 3) Ejecución de tests

Suite PHP (PHPUnit: unitarios + funcionales):

```bash
make test-unit
```

Alias corto:

```bash
make test
```

E2E headless (Playwright + Gherkin):

```bash
make test-e2e
```

E2E modo UI:

```bash
make test-e2e-ui
```

E2E modo debug:

```bash
make test-e2e-debug
```

Todo (suite PHP + e2e):

```bash
make test-all
```

Reporte Playwright:

```bash
make test-e2e-report
```

## 4) Comandos Docker y utilidades

Entorno dev:

```bash
make dev-up
make dev-down
make dev-build
make dev-restart
make dev-logs
make dev-ps
make dev-clean
```

Entorno test:

```bash
make test-up
make test-down
make test-build
make test-restart
make test-logs
make test-ps
make test-clean
```

Calidad backend:

```bash
make phpstan
```

## URLs de servicios

DEV:

- Frontend: http://localhost:8080/
- Architecture Monitor: http://localhost:8080/demo
- Explorer: http://localhost:8080/explorer
- API Docs: http://localhost:8080/docs
- Adminer: http://localhost:8081/
- Mongo Express: http://localhost:8082/
- n8n: http://localhost:5678/

TEST:

- App/API test: http://localhost:9080/
- Adminer test: http://localhost:9081/
- n8n test: http://localhost:9082/

## Notas

- Si cambias assets/frontend y vas a validar en `:9080`, usa `make test-init` o al menos `make test-up` para asegurar build Vite actualizado.
- La referencia arquitectónica principal para sesiones técnicas y de presentación es `TALK.md`.
