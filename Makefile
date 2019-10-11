.PHONY: it help stan test shell vendor

it: stan test ## Run useful checks before commits

help: ## Displays this list of targets with descriptions
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}'

stan: vendor ## Runs a static analysis with phpstan
#	docker-compose exec php vendor/bin/phpstan

test: vendor ## Runs tests with phpunit
	docker-compose exec php vendor/bin/phpunit

shell: vendor ## Open an interactive shell into the php container
	docker-compose exec php bash

vendor: composer.json composer.lock ## Install composer dependencies
	composer validate --strict
	composer install
	composer normalize
