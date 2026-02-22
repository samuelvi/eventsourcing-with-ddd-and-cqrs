# Variables
DOCKER_COMPOSE_DEV = docker compose -f docker/dev/docker-compose.yaml
DOCKER_COMPOSE_TEST = docker compose -f docker/test/docker-compose.yaml

ENV_FILE_BASE = .env .env.local
ENV_FILE_DEV = .env.dev .env.dev.local
ENV_FILE_TEST = .env.test .env.test.local

ENV_FILES_DEV = $(strip $(foreach f,$(ENV_FILE_BASE) $(ENV_FILE_DEV),$(if $(wildcard $(f)),--env-file $(f),)))
ENV_FILES_TEST = $(strip $(foreach f,$(ENV_FILE_BASE) $(ENV_FILE_TEST),$(if $(wildcard $(f)),--env-file $(f),)))

.PHONY: init dev-init test-init
.PHONY: dev-up dev-down dev-build dev-logs dev-ps dev-restart dev-clean dev-wait
.PHONY: test-up test-down test-build test-logs test-ps test-restart test-clean test-wait
.PHONY: setup-api setup-api-test load-fixtures load-fixtures-test init-front build-front
.PHONY: test test-unit test-e2e test-e2e-ui test-e2e-debug test-e2e-report test-all phpstan

# --- Inicialización DEV (de arriba a abajo) ---

init: dev-init

dev-init: dev-down dev-build dev-up dev-wait setup-api init-front load-fixtures
	@echo "Sistema dev inicializado."

dev-up:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) up -d

dev-down:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) down

dev-build:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) build

dev-wait:
	@echo "Esperando a que los servicios dev arranquen..."
	@sleep 10

setup-api:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) exec -T symfony-api composer install
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) exec -T symfony-api bin/console doctrine:migrations:migrate --no-interaction
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) restart messenger-worker

init-front:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) exec -T node npm install --quiet

load-fixtures:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) exec -T symfony-api sh -lc 'if bin/console list --raw | grep -q "^app:system:reset$$"; then bin/console app:system:reset --no-interaction; else echo "app:system:reset no disponible. Usando doctrine:fixtures:load."; bin/console doctrine:fixtures:load --no-interaction --append; fi'

# --- Inicialización TEST (de arriba a abajo) ---

test-init: test-down test-build test-up test-wait setup-api-test load-fixtures-test
	@echo "Sistema test inicializado."

test-up: build-front
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) up -d

test-down:
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) down

test-build:
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) build

test-wait:
	@echo "Esperando a que los servicios test arranquen..."
	@sleep 10

setup-api-test:
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) exec -T symfony-api-test composer install --no-interaction --prefer-dist --no-progress --no-scripts
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) exec -T symfony-api-test bin/console doctrine:database:create --if-not-exists
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) exec -T symfony-api-test bin/console doctrine:migrations:migrate --no-interaction
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) exec -T symfony-api-test bin/console cache:clear --env=test --no-warmup
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) exec -T symfony-api-test bin/console cache:warmup --env=test
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) exec -T symfony-api-test bin/console messenger:setup-transports --no-interaction
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) restart messenger-worker-test

load-fixtures-test:
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) exec -T symfony-api-test sh -lc 'if bin/console list --raw | grep -q "^app:system:reset$$"; then bin/console app:system:reset --no-interaction; else echo "app:system:reset no disponible en test. Usando doctrine:fixtures:load."; bin/console doctrine:fixtures:load --no-interaction --append; fi'

build-front:
	npx vite build

# --- Tests (suite PHP + e2e) ---

test: test-unit

test-unit: test-init
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) exec -T symfony-api-test bin/phpunit

test-e2e: test-init ## Run Playwright E2E tests (Headless)
	@echo "Running E2E Tests (Headless)..."
	PLAYWRIGHT_BASE_URL=http://127.0.0.1:9080 npx bddgen && \
	PLAYWRIGHT_BASE_URL=http://127.0.0.1:9080 npx playwright test || ( \
		echo "E2E tests failed. Dumping logs:" && \
		$(DOCKER_COMPOSE_TEST) logs --tail=100 symfony-api-test && \
		$(DOCKER_COMPOSE_TEST) logs --tail=100 messenger-worker-test && \
		exit 1 \
	)

test-e2e-ui: test-init ## Run Playwright E2E tests (UI Mode)
	@echo "Running E2E Tests (UI Mode)..."
	PLAYWRIGHT_BASE_URL=http://127.0.0.1:9080 npx bddgen && PLAYWRIGHT_BASE_URL=http://127.0.0.1:9080 npx playwright test --ui

test-e2e-debug: test-init ## Run Playwright E2E tests (Debug Mode)
	@echo "Running E2E Tests (Debug Mode)..."
	PLAYWRIGHT_BASE_URL=http://127.0.0.1:9080 npx bddgen && PLAYWRIGHT_BASE_URL=http://127.0.0.1:9080 npx playwright test --debug

test-e2e-report: ## Show Playwright E2E report
	npx playwright show-report .playwright/report

test-all: test-init
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) exec -T symfony-api-test bin/phpunit
	PLAYWRIGHT_BASE_URL=http://127.0.0.1:9080 npx bddgen && \
	PLAYWRIGHT_BASE_URL=http://127.0.0.1:9080 npx playwright test || ( \
		echo "E2E tests failed. Dumping logs:" && \
		$(DOCKER_COMPOSE_TEST) logs --tail=100 symfony-api-test && \
		$(DOCKER_COMPOSE_TEST) logs --tail=100 messenger-worker-test && \
		exit 1 \
	)

phpstan:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) exec -T symfony-api vendor/bin/phpstan analyse --memory-limit=1G

# --- Comandos Docker varios ---

dev-logs:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) logs -f

dev-ps:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) ps

dev-restart:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) restart

dev-clean:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) down -v
	rm -rf docker/dev/postgres/data/*
	rm -rf docker/dev/postgres-queue/data/*
	rm -rf docker/dev/mongodb/data/*
	rm -rf docker/dev/redis/data/*
	rm -rf docker/dev/kafka/data/*
	rm -rf docker/dev/redisinsight/data/*
	@echo "Entorno dev limpiado."

test-logs:
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) logs -f

test-ps:
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) ps

test-restart:
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) restart

test-clean:
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) down -v
	rm -rf docker/test/postgres/data/*
	rm -rf docker/test/postgres-queue/data/*
	rm -rf docker/test/mongodb/data/*
	rm -rf docker/test/redis/data/*
	rm -rf docker/test/kafka/data/*
	rm -rf docker/test/redisinsight/data/*
	@echo "Entorno test limpiado."
