.PHONY: it
it: vendor stan test ## Run useful checks before commits

.PHONY: help
help: ## Displays this list of targets with descriptions
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: setup
setup: build vendor ## Setup the local environment

.PHONY: build
build: ## Build the local Docker containers
	docker-compose build --build-arg USER_ID=$(shell id -u) --build-arg GROUP_ID=$(shell id -g)

.PHONY: up
up: ## Bring up the docker-compose stack
	docker-compose up -d

.PHONY: stan
stan: up ## Runs static analysis with phpstan
	docker-compose exec php composer stan

.PHONY: test
test: up ## Runs tests with phpunit
	docker-compose exec php composer test

.PHONY: bench
bench: up ## Run benchmarks
	docker-compose exec php composer bench

.PHONY: rector
rector: up ## Automatic code fixes with rector
	docker-compose exec php composer rector

vendor: up composer.json ## Install composer dependencies
	docker-compose exec php composer update
	docker-compose exec php composer validate --strict
	docker-compose exec php composer normalize

.PHONY: php
php: up ## Open an interactive shell into the php container
	docker-compose exec php bash

.PHONY: node
node: up ## Open an interactive shell into the node container
	docker-compose exec node bash
