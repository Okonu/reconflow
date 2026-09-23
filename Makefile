SHELL := /bin/bash
COMPOSE := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
DC := $(COMPOSE) -p reconflow -f deploy/docker-compose.yml --env-file deploy/.env

.PHONY: install dev migrate seed test test-all lint format types build up down logs ps

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
	./vendor/bin/pest --group=slow,default
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
	@echo "ReconFlow: https://localhost:$$(grep -E '^HTTPS_PORT=' deploy/.env | cut -d= -f2)"

down:
	$(DC) down

logs:
	$(DC) logs -f --tail=100

ps:
	$(DC) ps
