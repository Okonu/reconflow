SHELL := /bin/bash
COMPOSE := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
ENV_FILE := $(if $(wildcard deploy/.env),deploy/.env,deploy/.env.example)
DC := $(COMPOSE) -p reconflow -f docker-compose.yml --env-file $(ENV_FILE)

.PHONY: install dev migrate seed test test-all lint format types build up down reset logs ps

install:
	composer install
	npm ci

dev:
	php artisan dev

migrate:
	php artisan migrate

seed:
	php artisan db:seed

test:
	./vendor/bin/pest
	npm run types

test-all:
	./vendor/bin/pest
	./vendor/bin/pest --group=slow
	npm run types

lint:
	./vendor/bin/pint --test
	./vendor/bin/phpstan analyse --memory-limit=1G --no-progress
	npm run lint

format:
	./vendor/bin/pint

types:
	npm run types

build:
	docker build -t reconflow:local .

up:
	$(DC) up -d --build
	@echo "ReconFlow: http://localhost:$$(grep -sE '^HTTP_PORT=' $(ENV_FILE) | cut -d= -f2 | grep . || echo 8080)"

down:
	$(DC) down

reset:
	$(DC) down -v

logs:
	$(DC) logs -f --tail=100

ps:
	$(DC) ps
