dcserver=$$(echo "docker-compose exec server")
dcapp=$$(echo "docker-compose exec --workdir=/workdir/audit/app server")
dcclient=$$(echo "docker-compose exec client")

.PHONY: help
help: ## Displays this list of targets with descriptions
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(firstword $(MAKEFILE_LIST)) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: setup
setup: build app app/vendor ## Setup the local environment

.PHONY: build
build: ## Build the local Docker containers
	docker-compose build --pull --build-arg USER_ID=$(shell id -u) --build-arg GROUP_ID=$(shell id -g)

app: up ## Install the app
	${dcserver} rm -rf audit/app
	${dcserver} composer create-project laravel/laravel audit/app

app/vendor: up ## Install the app dependencies
	${dcapp} composer require nuwave/lighthouse:dev-master
	${dcapp} rsync -r /workdir/ /workdir/audit/app/vendor/nuwave/lighthouse --exclude audit --exclude docs --exclude vendor --exclude .idea --exclude .git --delete
	${dcapp} php artisan vendor:publish --tag=lighthouse-schema

.PHONY: up
up: ## Bring up the docker-compose stack
	docker-compose up --detach

.PHONY: serve
serve: up ## Run the HTTP server
	${dcapp} php artisan serve --host=0.0.0.0

.PHONY: audit
audit: up ## Runs tests with PHPUnit
	${dcclient} deno test --allow-net

.PHONY: fmt
fmt: up ## Runs tests with PHPUnit
	${dcclient} deno fmt test.ts
