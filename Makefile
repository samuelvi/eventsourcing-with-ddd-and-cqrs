# Variables
DOCKER_COMPOSE_DEV = docker compose -f docker/dev/docker-compose.yaml
ENV_FILE_BASE = .env .env.local
ENV_FILE_ENV = .env.dev .env.dev.local
ENV_FILES = $(strip $(foreach f,$(ENV_FILE_BASE) $(ENV_FILE_ENV),$(if $(wildcard $(f)),--env-file $(f),)))

.PHONY: dev-up dev-down dev-build dev-logs dev-ps dev-restart
.PHONY: setup-api load-fixtures init-front init

# Levantar el entorno de desarrollo
dev-up:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES) up -d

# Detener el entorno de desarrollo
dev-down:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES) down

# Limpiar todo el entorno (contenedores, redes, volúmenes y datos locales)
dev-clean:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES) down -v
	rm -rf docker/dev/postgres/data/*
	@echo "Entorno limpiado. Ejecuta 'make init' para reconstruir."

# Reconstruir las imágenes de desarrollo
dev-build:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES) build

# Ver logs del entorno de desarrollo
dev-logs:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES) logs -f

# Listar contenedores de desarrollo
dev-ps:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES) ps

# Reiniciar el entorno de desarrollo
dev-restart:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES) restart

# Configurar la API Symfony (Instalar dependencias y actualizar DB)
setup-api:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES) exec -T symfony-api composer install
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES) exec -T symfony-api bin/console doctrine:schema:update --force

# Cargar datos de prueba (fixtures)
load-fixtures:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES) exec -T symfony-api bin/console doctrine:fixtures:load --no-interaction

# Inicializar Frontend (Node install)
init-front:
	$(DOCKER_COMPOSE_DEV) $(ENV_FILES) exec -T node npm install --quiet

# Inicializar todo el sistema desde cero

init: dev-down dev-build dev-up

	@echo "Esperando a que los servicios arranquen..."

	@sleep 10

	$(MAKE) setup-api

	$(MAKE) init-front

	$(MAKE) load-fixtures

	@echo "Sistema inicializado correctamente."

	@echo "Frontend: http://localhost:5173 (o via Symfony en http://localhost:8080)"

	@echo "API Docs: http://localhost:8080/docs"



# Ejecutar tests funcionales
test:
	$(DOCKER_COMPOSE_DEV) exec -T -e APP_ENV=test symfony-api bin/console doctrine:database:create --if-not-exists
	$(DOCKER_COMPOSE_DEV) exec -T -e APP_ENV=test symfony-api bin/console doctrine:schema:update --force
	$(DOCKER_COMPOSE_DEV) exec -T -e APP_ENV=test -e SYMFONY_DEPRECATIONS_HELPER=disabled symfony-api bin/phpunit

# Ejecutar análisis estático con PHPStan
phpstan:
	$(DOCKER_COMPOSE_DEV) exec -T symfony-api vendor/bin/phpstan analyse --memory-limit=1G




