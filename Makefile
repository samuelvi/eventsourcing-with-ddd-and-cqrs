# Variables
DOCKER_COMPOSE_DEV = docker compose -f docker/dev/docker-compose.yaml
DOCKER_COMPOSE_TEST = docker compose -f docker/test/docker-compose.yaml

ENV_FILE_BASE = .env .env.local
ENV_FILE_DEV = .env.dev .env.dev.local
ENV_FILE_TEST = .env.test .env.test.local

ENV_FILES_DEV = $(strip $(foreach f,$(ENV_FILE_BASE) $(ENV_FILE_DEV),$(if $(wildcard $(f)),--env-file $(f),)))
ENV_FILES_TEST = $(strip $(foreach f,$(ENV_FILE_BASE) $(ENV_FILE_TEST),$(if $(wildcard $(f)),--env-file $(f),)))

.PHONY: dev-up dev-down dev-build dev-logs dev-ps dev-restart
.PHONY: test-up test-down test-build test-logs
.PHONY: setup-api load-fixtures init-front init test phpstan

# --- Entorno de Desarrollo ---

dev-up:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) up -d

dev-down:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) down

dev-clean:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) down -v
	rm -rf docker/dev/postgres/data/*
	rm -rf docker/dev/mongodb/data/*
	@echo "Entorno dev limpiado."

dev-build:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) build

dev-logs:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) logs -f

dev-ps:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) ps

dev-restart:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) restart

# --- E2E Testing (Playwright BDD) ---

test-e2e: ## Run Playwright E2E tests (Headless)
	@echo "Running E2E Tests (Headless)..."
	PLAYWRIGHT_BASE_URL=http://localhost:9080 npx bddgen && npx playwright test

test-e2e-ui: ## Run Playwright E2E tests (UI Mode)
	@echo "Running E2E Tests (UI Mode)..."
	PLAYWRIGHT_BASE_URL=http://localhost:9080 npx bddgen && npx playwright test --ui

test-e2e-debug: ## Run Playwright E2E tests (Debug Mode)
	@echo "Running E2E Tests (Debug Mode)..."
	PLAYWRIGHT_BASE_URL=http://localhost:9080 npx bddgen && npx playwright test --debug

test-e2e-report: ## Show Playwright E2E report
	npx playwright show-report var/log/playwright/report

# --- Entorno de Test ---

test-up:
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) up -d

test-down:
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) down

test-build:
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) build

test-logs:
	$(DOCKER_COMPOSE_TEST) $(ENV_FILES_TEST) logs -f

# --- Comandos de Aplicación ---

setup-api:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) exec -T symfony-api composer install
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) exec -T symfony-api bin/console doctrine:migrations:migrate --no-interaction

load-fixtures:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) exec -T symfony-api bin/console doctrine:fixtures:load --no-interaction

init-front:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES_DEV) exec -T node npm install --quiet

init: dev-down dev-build dev-up
	@echo "Esperando a que los servicios dev arranquen..."
	@sleep 10
	$(MAKE) setup-api
	$(MAKE) init-front
	$(MAKE) load-fixtures
	@echo "Sistema dev inicializado."

# --- Ejecución de Tests ---

test: test-up
	@echo "Esperando a que los servicios de test estén listos..."
	@sleep 10
	$(DOCKER_COMPOSE_TEST) exec -T symfony-api-test bin/console doctrine:database:create --if-not-exists
	$(DOCKER_COMPOSE_TEST) exec -T symfony-api-test bin/console doctrine:migrations:migrate --no-interaction
	$(DOCKER_COMPOSE_TEST) exec -T symfony-api-test bin/phpunit
	$(MAKE) test-down

phpstan:
	$(DOCKER_COMPOSE_DEV) exec -T symfony-api vendor/bin/phpstan analyse --memory-limit=1G
