.PHONY: it
it: vendor fix stan test ## Run useful checks before commits

.PHONY: help
help: ## Displays this list of targets with descriptions
	@grep --extended-regexp '^\.?[a-zA-Z0-9_-]+:.*?## .*$$' $(firstword $(MAKEFILE_LIST)) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: setup
setup: build vendor docs/node_modules ai-sync ## Prepare the local environment

.PHONY: build
build: ## Build the local Docker containers
	# Using short options of `id` to ensure compatibility with macOS, see https://github.com/nuwave/lighthouse/pull/2504
	docker compose build --pull --build-arg="USER_ID=$(shell id -u)" --build-arg="GROUP_ID=$(shell id -g)"

.PHONY: fix
fix: rector rector-benchmarks php-cs-fixer prettier ## Automatically refactor and format code

.PHONY: rector
rector: ## Refactor code with Rector
	docker compose run --rm --no-deps php vendor/bin/rector process

.PHONY: php-cs-fixer
php-cs-fixer: ## Format code with php-cs-fixer
	docker compose run --rm --no-deps php vendor/bin/php-cs-fixer fix

.PHONY: prettier
prettier: ## Format code with prettier
	docker compose run --rm node-docs yarn run prettify

.PHONY: stan
stan: stan-benchmarks ## Run static analysis with PHPStan
	docker compose run --rm --no-deps php vendor/bin/phpstan --verbose

.PHONY: test
test: ## Run tests with PHPUnit
	docker compose run --rm php vendor/bin/phpunit

ifdef GITHUB_ACTIONS
benchmarks-github-actions=--env=GITHUB_ACTIONS --env=GITHUB_WORKSPACE=/app --env=GITHUB_STEP_SUMMARY --volume="$(GITHUB_STEP_SUMMARY):$(GITHUB_STEP_SUMMARY)"
endif
run-benchmarks=docker build --quiet --tag=lighthouse-benchmarks benchmarks > /dev/null && docker run --rm --volume="$(CURDIR):/app" --user="$(shell id -u):$(shell id -g)" --env=COMPOSER_HOME=/tmp/composer $(benchmarks-github-actions) lighthouse-benchmarks

benchmarks/vendor: benchmarks/composer.json benchmarks/composer.lock
	$(run-benchmarks) composer validate --no-check-publish
	$(run-benchmarks) composer install
	touch benchmarks/vendor

.PHONY: bench
bench: benchmarks/vendor ## Count benchmark instructions with Tally
	$(run-benchmarks) vendor/bin/tally run --output=tally-results

.PHONY: bench-update
bench-update: ## Update the dependencies the benchmarks are counted with
	$(run-benchmarks) composer update

.PHONY: bench-accept
bench-accept: benchmarks/vendor ## Accept the counts of the CI run RUN as expected
	$(if $(RUN),,$(error Pass the ID of the CI run, e.g. make bench-accept RUN=123))
	rm -rf benchmarks/tally-results
	gh run download $(RUN) --repo=nuwave/lighthouse --name=tally-results --dir=benchmarks/tally-results
	$(run-benchmarks) vendor/bin/tally accept tally-results

.PHONY: stan-benchmarks
stan-benchmarks: benchmarks/vendor ## Run static analysis on the benchmarks with PHPStan
	$(run-benchmarks) vendor/bin/phpstan

.PHONY: rector-benchmarks
rector-benchmarks: benchmarks/vendor ## Refactor the benchmarks with Rector
	$(run-benchmarks) vendor/bin/rector process

vendor: composer.json ## Install composer dependencies
	docker compose run --rm --no-deps php composer update
	docker compose run --rm --no-deps php composer validate --strict
	docker compose run --rm --no-deps php composer normalize

.PHONY: php
php: ## Open an interactive shell into the PHP container
	docker compose run --rm php bash

.PHONY: node
node: ## Open an interactive shell into the Node container
	docker compose run --rm node-docs bash

.PHONY: release
release: ## Prepare the docs for a new release
	rm -rf docs/6 && cp -r docs/master docs/6

.PHONY: docs
docs: ## Render the docs in a development server
	docker compose run --rm --service-ports node-docs yarn run start

docs/node_modules: docs/package.json docs/yarn.lock ## Install yarn dependencies
	docker compose run --rm node-docs yarn

.PHONY: ai-sync
ai-sync: ## Generate local agent configuration from .ai
	# https://github.com/KrystianJonca/lnai/releases
	docker compose run --rm node-tools npx --yes lnai@0.6.92 sync

.PHONY: proto/update-reports
proto/update-reports:
	docker compose run --rm --no-deps php bin/update-reports-proto.sh

.PHONY: proto
proto:
	docker run --rm --volume=.:/workdir --workdir=/workdir --pull=always bufbuild/buf generate
	# Using short options of `id` to ensure compatibility with macOS, see https://github.com/nuwave/lighthouse/pull/2504
	sudo chown --recursive "$(shell id -u):$(shell id -g)" proto-tmp
	bin/install-generated-proto.sh
