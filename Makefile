# Everything runs in containers: Docker is the only local requirement.
PHP_IMAGE := serversideup/php:8.4-fpm-nginx-alpine3.24-v5.0.5
PHP := docker run --rm -u root -v "$(CURDIR)":/app -w /app --entrypoint "" $(PHP_IMAGE)
APP_PORT ?= 8080
export APP_PORT

.PHONY: help install lint analyse test check build up down smoke scan hadolint ci

help: ## Show available targets
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  %-10s %s\n", $$1, $$2}'

install: ## Install PHP dependencies
	docker run --rm -v "$(CURDIR)":/app -w /app composer:2.10.3 composer install --no-interaction

lint: ## Check code style (Pint)
	$(PHP) vendor/bin/pint --test

analyse: ## Static analysis (PHPStan level 8)
	$(PHP) vendor/bin/phpstan analyse --no-progress --memory-limit=512M

test: ## Run the test suite (SQLite in memory)
	$(PHP) vendor/bin/phpunit

check: lint analyse test ## Lint, analyse and test

build: ## Build the production image
	docker compose build app

up: ## Start app + PostgreSQL + Redis on http://127.0.0.1:$(APP_PORT)
	docker compose up --detach --wait

down: ## Stop the stack and delete its data
	docker compose down --volumes

smoke: ## Smoke test and failure drill against the running stack
	scripts/smoke-test.sh

scan: ## Scan the image for fixable HIGH/CRITICAL vulnerabilities
	docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy:0.67.2 \
		image --severity HIGH,CRITICAL --ignore-unfixed --exit-code 1 laravel-cicd-pipeline:local

hadolint: ## Lint the Dockerfile
	docker run --rm -i hadolint/hadolint:v2.12.0 < Dockerfile

ci: check hadolint build up smoke scan down ## Run the whole pipeline locally
