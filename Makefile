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

init-front:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) exec -T node npm install --quiet

load-fixtures:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) exec -T symfony-api bin/console app:system:reset --no-interaction

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
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) exec -T symfony-api-test bin/console doctrine:database:create --if-not-exists
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) exec -T symfony-api-test bin/console doctrine:migrations:migrate --no-interaction

load-fixtures-test:
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) exec -T symfony-api-test bin/console app:system:reset --no-interaction

build-front:
	npx vite build

# --- Tests (unitarios + e2e) ---

test: test-unit

test-unit: test-init
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) exec -T symfony-api-test bin/phpunit

test-e2e: test-init ## Run Playwright E2E tests (Headless)
	@echo "Running E2E Tests (Headless)..."
	PLAYWRIGHT_BASE_URL=http://localhost:9080 npx bddgen && npx playwright test

test-e2e-ui: test-init ## Run Playwright E2E tests (UI Mode)
	@echo "Running E2E Tests (UI Mode)..."
	PLAYWRIGHT_BASE_URL=http://localhost:9080 npx bddgen && npx playwright test --ui

test-e2e-debug: test-init ## Run Playwright E2E tests (Debug Mode)
	@echo "Running E2E Tests (Debug Mode)..."
	PLAYWRIGHT_BASE_URL=http://localhost:9080 npx bddgen && npx playwright test --debug

test-e2e-report: ## Show Playwright E2E report
	npx playwright show-report var/log/playwright/report

test-all: test-init
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) exec -T symfony-api-test bin/phpunit
	PLAYWRIGHT_BASE_URL=http://localhost:9080 npx bddgen && npx playwright test

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
	rm -rf docker/dev/mongodb/data/*
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
	rm -rf docker/test/mongodb/data/*
	@echo "Entorno test limpiado."
