# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Lighthouse is a GraphQL framework for Laravel that uses a schema-first approach with directives.
It integrates `webonyx/graphql-php` with Laravel's ecosystem.

## Development Commands

The project uses Docker + Make for a reproducible development environment.

```bash
make setup          # Initial setup: build containers, install dependencies
make                # Run all checks before committing (fix, stan, test)
make fix            # Auto-format code (rector, php-cs-fixer, prettier)
make stan           # Static analysis with PHPStan
make test           # Run PHPUnit tests
make bench          # Run PHPBench benchmarks
make php            # Shell into PHP container
```

### Running a Single Test

```bash
docker-compose exec php vendor/bin/phpunit --filter=TestClassName
docker-compose exec php vendor/bin/phpunit --filter=testMethodName
docker-compose exec php vendor/bin/phpunit tests/Unit/Path/To/TestFile.php
```

## Architecture

### Entry Points

- `src/LighthouseServiceProvider.php` - Main service provider, registers singletons and bindings
- `src/GraphQL.php` - Main entrypoint to GraphQL execution (`@api` marked)
- `src/Http/routes.php` - GraphQL endpoint routing

### Schema Processing Pipeline

1. **Schema Source** (`src/Schema/Source/`) - `SchemaStitcher` loads and combines `.graphql` files
2. **AST Building** (`src/Schema/AST/`) - `ASTBuilder` parses schema into AST nodes
3. **Schema Building** (`src/Schema/SchemaBuilder.php`) - Builds executable GraphQL schema
4. **Type Registry** (`src/Schema/TypeRegistry.php`) - Manages GraphQL types

### Directive System

Directives are the core extension mechanism. Located in `src/Schema/Directives/`.

- `BaseDirective` - Abstract base class for all directives, provides common utilities
- Directive interfaces in `src/Support/Contracts/` define capabilities:
  - `FieldResolver` - Resolves field values
  - `FieldMiddleware` - Wraps field resolution
  - `ArgTransformerDirective` - Transforms argument values
  - `ArgBuilderDirective` - Modifies query builder
  - `TypeManipulator`, `FieldManipulator`, `ArgManipulator` - Schema manipulation

Directives are named by convention: `FooDirective` maps to `@foo` in GraphQL schema.

### Service Providers

Multiple service providers for optional features (auto-discovered via composer.json):
- `AuthServiceProvider` - Authentication directives (@auth, @can, @guard)
- `CacheServiceProvider` - Query result caching (@cache)
- `PaginationServiceProvider` - Pagination types and directives
- `ValidationServiceProvider` - Input validation (@rules)
- `SoftDeletesServiceProvider`, `GlobalIdServiceProvider`, `OrderByServiceProvider`

### Testing Infrastructure

- `tests/TestCase.php` - Base test class using Orchestra Testbench
- `tests/DBTestCase.php` - Tests requiring database (MySQL)
- `MakesGraphQLRequests` trait - `$this->graphQL($query)` helper for testing
- `MocksResolvers` trait - Mock field resolvers
- `UsesTestSchema` trait - Set schema via `$this->schema = '...'`

Tests use `Tests\Utils\` namespace for test fixtures (Models, Queries, Mutations, etc.).

## Code Style

- PHPStan level 8
- php-cs-fixer with `mll-lab/php-cs-fixer-config` (risky rules)
- `protected` over `private` for extensibility
- Never use `final` in `src/`,  always in `tests/`
- Full namespace in PHPDoc (`@var \Full\Namespace\Class`), imports in code
- Code elements with `@api` have stability guarantees between major versions

## Pull Requests

Follow the [PR template](.github/PULL_REQUEST_TEMPLATE.md):
- Link related issues
- Add or update tests
- Document user-facing changes in `/docs`
- Update `CHANGELOG.md`

### Changelog

Add entries to the `## Unreleased` section in [CHANGELOG.md](/CHANGELOG.md).
Use categories: `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`.
End each entry with a link to the PR.

See [CONTRIBUTING.md](/CONTRIBUTING.md) for full guidelines.
