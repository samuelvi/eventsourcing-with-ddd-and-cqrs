# Event Sourcing With DDD & CQRS

POC de reservas con Event Sourcing usando Symfony 7.4 LTS, PHP 8.4/8.5, API Platform 3.4, React 18 (Vite), PostgreSQL, MongoDB y n8n.

## Estado del proyecto

- Stack backend orientado a DDD + CQRS + Event Sourcing.
- Frontend SPA servido por Symfony con assets Vite.
- Entornos Docker separados para `dev` y `test`.

## Documentación técnica

Para arquitectura, decisiones técnicas, flujo de reservas, snapshots, consistencia eventual y roadmap de sagas/orquestación:

- `TALK.md`

Este `README.md` está enfocado en operación diaria (arranque, tests y comandos).

## Herramienta Symfony (CLI)

La tool de Symfony que buscas es la CLI `symfony`.

En este proyecto no viene instalada por defecto, así que si quieres usarla:

```bash
curl -sS https://get.symfony.com/cli/installer | bash
export PATH="$HOME/.symfony5/bin:$PATH"
```

Comprueba que quedó disponible con:

```bash
symfony --version
```

Si quieres, también la puedo integrar con un target del `Makefile` para lanzarla desde aquí.

## Symfony Profiler (DEV)

Ya instalé el Web Profiler para `dev` (`symfony/web-profiler-bundle`) y quedaron sus rutas/ajustes en:

- `config/bundles.php`
- `config/packages/web_profiler.yaml`
- `config/routes/web_profiler.yaml`
- Versión instalada: `symfony/web-profiler-bundle ^7.4` (solo entorno `dev`/`test` en desarrollo)

Con `APP_ENV=dev` y `APP_DEBUG=1` (como en `docker/dev/docker-compose.yaml`), debes ver la barra en respuestas HTML:

- Toolbar: `http://localhost:8080/_wdt/{token}` (el token lo da la barra o entra por `_profiler`)
- Panel: `http://localhost:8080/_profiler`

Si no aparece, intenta:

```bash
make dev-down && make dev-up
```

y vuelve a abrir una respuesta HTML desde la app (el profiler no suele mostrarse en respuestas JSON crudas de API).

## Flujo recomendado (rápido)

1. Levantar entorno de desarrollo:

```bash
make dev-init
```

2. Levantar entorno de pruebas:

```bash
make test-init
```

3. Ejecutar tests unitarios:

```bash
make test
```

4. Ejecutar E2E:

```bash
make test-e2e
```

## Inicialización de entornos

### DEV

Comando principal:

```bash
make dev-init
```

Alias equivalente:

```bash
make init
```

Secuencia:

1. `dev-down`
2. `dev-build`
3. `dev-up`
4. `dev-wait`
5. `setup-api`
6. `init-front`
7. `load-fixtures`

### TEST

Comando principal:

```bash
make test-init
```

Secuencia:

1. `test-down`
2. `test-build`
3. `test-up` (incluye `build-front`)
4. `test-wait`
5. `setup-api-test`
6. `load-fixtures-test`

Nota:

- En `test` (`http://localhost:9080`) no hay Vite dev server.
- Por eso `test-up` compila frontend con `npx vite build` antes de levantar contenedores.

## Ejecución de tests

PHPUnit (unitarios):

```bash
make test-unit
```

Alias corto:

```bash
make test
```

E2E headless (Playwright + Gherkin + Playwright BDD):

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

Suite completa (PHP + E2E):

```bash
make test-all
```

Reporte Playwright:

```bash
make test-e2e-report
```

### Cómo encaja `npx bddgen`

Los targets E2E ejecutan internamente `npx bddgen` antes de `playwright test`.

- `bddgen` transforma los `.feature` (Gherkin) y `*.steps.ts` en tests ejecutables para Playwright.
- Sin ese paso de generación, Playwright no ejecuta escenarios BDD.

### Comportamientos implícitos en E2E

- `@reset`: limpia estado vía `/api/test/reset-db-empty` antes del escenario.
- `@no-reset`: fuerza reutilizar estado (sin limpieza), útil para escenarios encadenados.
- `PLAYWRIGHT_BASE_URL`: define la base URL (fallback: `http://localhost:9080`).
- En CI hay reintentos automáticos y ejecución secuencial según la configuración de workers/features.

## Comandos Docker y utilidades

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

## Mensajería, Reintentos y Dead Letter (failed)

Configuración activa en `config/packages/messenger.php` con switch por entorno:

- `QUEUE_BACKEND=postgres|redis|kafka`
- Transportes activos:
    - `postgres` -> `doctrine://` (tabla `messenger_messages`)
    - `redis` -> `appredis://` (listas/sets Redis)
    - `kafka` -> `appkafka://` (topics Kafka)
- Transporte `failed` dedicado para DLQ en los tres backends.

Parámetros de reintento (`async`):

- `max_retries: 5`
- `delay: 1000ms`
- `multiplier: 2`
- `max_delay: 60000ms`
- `jitter: 0.1`

Cuando se agotan los reintentos, el mensaje se mueve al transporte `failed` (DLQ) y **no se elimina automáticamente**.

Comandos operativos (DEV):

```bash
docker compose -f docker/dev/docker-compose.yaml --env-file .env --env-file .env.dev exec -T symfony-api bin/console messenger:failed:show
docker compose -f docker/dev/docker-compose.yaml --env-file .env --env-file .env.dev exec -T symfony-api bin/console messenger:failed:retry --force
docker compose -f docker/dev/docker-compose.yaml --env-file .env --env-file .env.dev exec -T symfony-api bin/console messenger:failed:remove --force
```

Comandos operativos (TEST):

```bash
docker compose -f docker/test/docker-compose.yaml --env-file .env --env-file .env.test exec -T symfony-api-test bin/console messenger:failed:show
docker compose -f docker/test/docker-compose.yaml --env-file .env --env-file .env.test exec -T symfony-api-test bin/console messenger:failed:retry --force
docker compose -f docker/test/docker-compose.yaml --env-file .env --env-file .env.test exec -T symfony-api-test bin/console messenger:failed:remove --force
```

Observabilidad rápida:

- UI: `http://localhost:8080/demo` muestra `Queue Pending` y `Failed`.
- Panel de control: `http://localhost:8080/panel` (accesos a Adminer, RedisInsight, Kafka UI, Mongo Express y Supervisor).
- API: `GET /api/demo/stats` devuelve:
    - `queue.backend`
    - `queue.async`
    - `queue.failed`

Consulta SQL directa (solo backend `postgres`):

```bash
# DEV
docker compose -f docker/dev/docker-compose.yaml --env-file .env --env-file .env.dev exec -T queue-db psql -U queue_user -d messenger_queue -c "SELECT queue_name, COUNT(*) FROM messenger_messages GROUP BY queue_name ORDER BY queue_name;"

# TEST
docker compose -f docker/test/docker-compose.yaml --env-file .env --env-file .env.test exec -T queue-db-test psql -U queue_user -d messenger_queue_test -c "SELECT queue_name, COUNT(*) FROM messenger_messages GROUP BY queue_name ORDER BY queue_name;"
```

Cambio rápido de backend:

```bash
# Edita QUEUE_BACKEND en .env.dev o .env.test (postgres|redis|kafka)
# Ejemplo DEV:
# QUEUE_BACKEND=redis
make dev-down && make dev-up

# Ejemplo TEST:
# QUEUE_BACKEND=kafka
make test-down && make test-up
```

## URLs de servicios

DEV:

- Frontend: http://localhost:8080/
- Architecture Monitor: http://localhost:8080/demo
- Explorer: http://localhost:8080/explorer
- API Docs: http://localhost:8080/docs
- Adminer: http://localhost:8081/ (Servidores: `postgres-db`, `queue-db`)
- RedisInsight: http://localhost:8084/
- Kafka UI: http://localhost:8085/
- Supervisor Web UI: http://localhost:9001/ (Gestión de workers)
- Mongo Express: http://localhost:8082/
- n8n: http://localhost:5678/

TEST:

- App/API: http://localhost:9080/
- Adminer: http://localhost:9081/ (Servidores: `postgres-db-test`, `queue-db-test`)
- RedisInsight: http://localhost:9084/
- Kafka UI: http://localhost:9085/
- Supervisor Web UI: http://localhost:9002/ (Gestión de workers test)
- n8n: http://localhost:9082/
- Mongo Express: http://localhost:9083/

Credenciales Mongo Express (dev y test):

- Usuario: `user`
- Password: `password`

## Credenciales PostgreSQL

### Dominio (Agregados y Proyecciones)

DEV:

- Host: `postgres-db`
- Puerto: `5432`
- Base de datos: `event_sourcing_dev`
- Usuario: `user`
- Password: `password`

TEST:

- Host: `postgres-db-test`
- Puerto: `5432`
- Base de datos: `event_sourcing_test`
- Usuario: `user`
- Password: `password`

### Mensajería (Messenger Queue)

DEV:

- Host: `queue-db`
- Puerto: `5432`
- Base de datos: `messenger_queue`
- Usuario: `queue_user`
- Password: `queue_password`

TEST:

- Host: `queue-db-test`
- Puerto: `5432`
- Base de datos: `messenger_queue_test`
- Usuario: `queue_user`
- Password: `queue_password`

### Redis Queue Backend

DEV:

- Host: `redis`
- Puerto: `6379`

TEST:

- Host: `redis-test`
- Puerto: `6379`

### Kafka Queue Backend

DEV:

- Broker: `kafka:9092`
- Topics usados: `async`, `failed`

TEST:

- Broker: `kafka-test:9092`
- Topics usados: `async`, `failed`

## Gestión de Mensajería Asíncrona (Messenger)

El sistema utiliza **Symfony Messenger** gestionado por **Supervisor** para el procesamiento asíncrono de comandos y eventos.

### Monitorización y Control

- **Supervisor Web UI (http://localhost:9001)**: Permite ver el estado de los procesos worker, reiniciarlos y ver sus logs directamente desde el navegador sin entrar al contenedor.
- **Adminer (http://localhost:8081)**: Permite inspeccionar la tabla `messenger_messages` seleccionando el servidor `queue-db`.

### Dead Letter Queue (DLQ)

Para garantizar que no se pierda ningún dato ante fallos puntuales (ej. el caso del Usuario B que falla mientras A y C funcionan):

1. Los mensajes fallidos se reintentan automáticamente con un retardo exponencial.
2. Tras agotar los reintentos, el mensaje se mueve a la cola `failed` en la base de datos `queue-db`.
3. El sistema continúa operando, y los mensajes en la DLQ esperan intervención manual.

**Comandos útiles:**

```bash
# Ver estado de las colas
docker exec -it messenger-worker bin/console messenger:stats

# Ver mensajes fallidos
docker exec -it messenger-worker bin/console messenger:failed:show

# Recuperar/Reintentar mensajes fallidos
docker exec -it messenger-worker bin/console messenger:failed:retry
```

## Notas

- Si cambias frontend y validas en `:9080`, usa `make test-init` o al menos `make test-up` para asegurar build Vite actualizado.
- Si necesitas levantar solo Mongo Express en test: `docker compose -f docker/test/docker-compose.yaml up -d mongo-express-test`.
- La referencia arquitectónica principal para sesiones técnicas y de presentación es `TALK.md`.
