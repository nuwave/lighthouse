dcclient=$$(echo "docker-compose exec -T client")

.PHONY: help
help: ## Displays this list of targets with descriptions
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(firstword $(MAKEFILE_LIST)) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: setup
setup: build ## Setup the local environment

.PHONY: build
build: ## Build the local Docker containers
	docker-compose build --pull

.PHONY: up
up: ## Bring up the docker-compose stack
	docker-compose up --detach

.PHONY: audit
audit: up ## Runs tests with PHPUnit
	${dcclient} deno test --allow-net

.PHONY: fmt
fmt: up ## Runs tests with PHPUnit
	${dcclient} deno fmt test.ts
