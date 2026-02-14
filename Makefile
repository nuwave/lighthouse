.PHONY: it
it: vendor fix stan test ## Run useful checks before commits

.PHONY: help
help: ## Display this list of targets with descriptions
	@grep --extended-regexp '^[a-zA-Z0-9_-]+:.*?## .*$$' $(firstword $(MAKEFILE_LIST)) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: setup
setup: build vendor docs/node_modules ai-sync ## Prepare the local environment

.PHONY: build
build: ## Build the local Docker containers
	# Using short options of `id` to ensure compatibility with macOS, see https://github.com/nuwave/lighthouse/pull/2504
	docker compose build --pull --build-arg="USER_ID=$(shell id -u)" --build-arg="GROUP_ID=$(shell id -g)"

.PHONY: up
up: ## Bring up the docker compose stack
	docker compose up --detach

.PHONY: fix
fix: rector php-cs-fixer prettier ## Automatically refactor and format code

.PHONY: rector
rector: up ## Refactor code with Rector
	docker compose exec php vendor/bin/rector process

.PHONY: php-cs-fixer
php-cs-fixer: up ## Format code with php-cs-fixer
	docker compose exec php vendor/bin/php-cs-fixer fix

.PHONY: prettier
prettier: up ## Format code with prettier
	docker compose exec node-docs yarn run prettify

.PHONY: stan
stan: up ## Run static analysis with PHPStan
	docker compose exec php vendor/bin/phpstan --verbose

.PHONY: test
test: up ## Run tests with PHPUnit
	docker compose exec php vendor/bin/phpunit

.PHONY: bench
bench: up ## Run benchmarks with PHPBench
	docker compose exec php vendor/bin/phpbench run --report=aggregate

vendor: up composer.json ## Install composer dependencies
	docker compose exec php composer update
	docker compose exec php composer validate --strict
	docker compose exec php composer normalize

.PHONY: php
php: up ## Open an interactive shell into the PHP container
	docker compose exec php bash

.PHONY: node
node: up ## Open an interactive shell into the Node container
	docker compose exec node-docs bash

.PHONY: release
release: ## Prepare the docs for a new release
	rm -rf docs/6 && cp -r docs/master docs/6

.PHONY: docs
docs: up ## Render the docs in a development server
	docker compose exec node-docs yarn run start

docs/node_modules: up docs/package.json docs/yarn.lock ## Install yarn dependencies
	docker compose exec node-docs yarn

.PHONY: ai-sync
ai-sync: up ## Generate local agent configuration from .ai
	# https://github.com/KrystianJonca/lnai/releases
	docker compose exec node-tools npx --yes lnai@0.6.7 sync

.PHONY: proto/update-reports
proto/update-reports:
	docker compose exec php curl --silent --show-error --fail --output src/Tracing/FederatedTracing/reports.proto https://usage-reporting.api.apollographql.com/proto/reports.proto
	docker compose exec php sed --in-place 's/ \[(js_use_toArray) = true]//g' src/Tracing/FederatedTracing/reports.proto
	docker compose exec php sed --in-place 's/ \[(js_preEncoded) = true]//g' src/Tracing/FederatedTracing/reports.proto
	docker compose exec php sed --in-place '3 i option php_namespace = "Nuwave\\\\Lighthouse\\\\Tracing\\\\FederatedTracing\\\\Proto";' src/Tracing/FederatedTracing/reports.proto
	docker compose exec php sed --in-place '4 i option php_metadata_namespace = "Nuwave\\\\Lighthouse\\\\Tracing\\\\FederatedTracing\\\\Proto\\\\Metadata";' src/Tracing/FederatedTracing/reports.proto

.PHONY: proto
proto:
	docker run --rm --volume=.:/workdir --workdir=/workdir --pull=always bufbuild/buf generate
	rm -rf src/Tracing/FederatedTracing/Proto
	# Using short options of `id` to ensure compatibility with macOS, see https://github.com/nuwave/lighthouse/pull/2504
	sudo chown --recursive "$(shell id -u):$(shell id -g)" proto-tmp
	mv proto-tmp/Nuwave/Lighthouse/Tracing/FederatedTracing/Proto src/Tracing/FederatedTracing/Proto
	rm -rf proto-tmp
