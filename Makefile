dcphp=$$(echo "docker-compose exec php")
dcnode=$$(echo "docker-compose exec node")

.PHONY: it
it: vendor fix stan test ## Run useful checks before commits

.PHONY: help
help: ## Displays this list of targets with descriptions
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(firstword $(MAKEFILE_LIST)) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: setup
setup: build docs/node_modules vendor ## Setup the local environment

.PHONY: build
build: ## Build the local Docker containers
	docker-compose build --pull --build-arg USER_ID=$(shell id --user) --build-arg GROUP_ID=$(shell id --group)

.PHONY: up
up: ## Bring up the docker-compose stack
	docker-compose up --detach

.PHONY: fix
fix: rector php-cs-fixer prettier ## Automatic code fixes

.PHONY: rector
rector: up ## Automatic code fixes with Rector
	${dcphp} vendor/bin/rector process

.PHONY: php-cs-fixer
php-cs-fixer: up ## Format code with php-cs-fixer
	${dcphp} vendor/bin/php-cs-fixer fix

.PHONY: prettier
prettier: up ## Format code with prettier
	${dcnode} yarn run prettify

.PHONY: stan
stan: up ## Runs static analysis
	${dcphp} vendor/bin/phpstan

.PHONY: test
test: up ## Runs tests with PHPUnit
	${dcphp} vendor/bin/phpunit

.PHONY: bench
bench: up ## Run benchmarks
	${dcphp} vendor/bin/phpbench run --report=aggregate

vendor: up composer.json ## Install composer dependencies
	${dcphp} composer update
	${dcphp} composer validate --strict
	${dcphp} composer normalize

.PHONY: php
php: up ## Open an interactive shell into the PHP container
	${dcphp} bash

.PHONY: node
node: up ## Open an interactive shell into the Node container
	${dcnode} bash

.PHONY: release
release: ## Prepare the docs for a new release
	rm -rf docs/6 && cp -r docs/master docs/6

.PHONY: docs
docs: up ## Render the docs in a development server
	${dcnode} yarn run start

docs/node_modules: up docs/package.json docs/yarn.lock ## Install yarn dependencies
	${dcnode} yarn
