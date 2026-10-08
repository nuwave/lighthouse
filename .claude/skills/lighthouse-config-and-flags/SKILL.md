---
name: lighthouse-config-and-flags
description: >-
  The catalog of every Lighthouse configuration option and environment variable —
  key, default, env var, and whether it is production-ready or experimental. Load
  when reading, changing, or documenting a config value; when adding a new config
  option; when a bug depends on a flag (schema_cache, query_cache, batchload_relations,
  transactional_mutations, force_fill, defer, tracing, subscriptions); or when you
  need the exact publish command or env-var name. Includes a checklist for adding an
  option without breaking backward compatibility, plus drift-check commands.
---

# Lighthouse config and flags

The single source of truth is `src/lighthouse.php` (547 lines). Users publish it to `config/lighthouse.php`.
Config is read two ways in the codebase: via the `config('lighthouse.…')` helper (e.g. `src/Schema/DirectiveLocator.php:61`) and via an injected `Illuminate\Contracts\Config\Repository` (preferred per the no-Facades rule — the `config()` helper is tolerated).

Ground truth date: 2026-07-07, v6.68.0 (`master` @ a29ff8e). Every default below was read from `src/lighthouse.php`. Env-var defaults are the fallback in the `env(...)` call.

## Publishing the config

```bash
php artisan vendor:publish --tag=lighthouse-config    # publishes src/lighthouse.php → config/lighthouse.php
php artisan vendor:publish --tag=lighthouse-schema     # publishes default-schema.graphql → your schema_path
```
Tags verified in `src/LighthouseServiceProvider.php` (`lighthouse-config`, `lighthouse-schema`).

## Full option catalog

Legend: **Prod** = stable, safe in production. **Exp** = experimental / handle with care. **Perf** = performance tuning, verify with benchmarks.

| Key | Default | Env var | Class | Notes |
|---|---|---|---|---|
| `route.uri` | `/graphql` | — | Prod | The endpoint URI. |
| `route.name` | `graphql` | — | Prod | Named route. |
| `route.middleware` | `AcceptJson`, `AttemptAuthentication` (+ commented `EnsureXHR`, `LogGraphQLQueries`) | — | Prod | Runs before execution. `EnsureXHR` is **default-on in v7** (`UPGRADE.md`). |
| `guards` | `null` | — | Prod | Auth guards for `@guard`/`AttemptAuthentication`; `null` = Laravel default. |
| `schema_path` | `base_path('graphql/schema.graphql')` | — | Prod | Root `.graphql` file. |
| `schema_cache.enable` | `env('APP_ENV') !== 'local'` | `LIGHTHOUSE_SCHEMA_CACHE_ENABLE` | Prod/Perf | On by default outside `local`. Stale cache = common trap (`lighthouse-debugging-playbook`). |
| `schema_cache.path` | `bootstrap/cache/lighthouse-schema.php` | `LIGHTHOUSE_SCHEMA_CACHE_PATH` | Prod | Serialized AST/schema file. |
| `cache_directive_tags` | `false` | — | Prod | `@cache` uses a tagged cache; requires a taggable store. |
| `query_cache.enable` | `true` | `LIGHTHOUSE_QUERY_CACHE_ENABLE` | Perf | Caches parsed query strings. |
| `query_cache.mode` | `store` | `LIGHTHOUSE_QUERY_CACHE_MODE` | Perf | `store` (shared cache) / `opcache` (local PHP files) / `hybrid`. Added #2713; hybrid+APQ fixed #2727. |
| `query_cache.opcache_path` | `bootstrap/cache` | `LIGHTHOUSE_QUERY_CACHE_OPCACHE_PATH` | Perf | Folder; one file per query. Only for `opcache`/`hybrid`. |
| `query_cache.store` | `null` | `LIGHTHOUSE_QUERY_CACHE_STORE` | Perf | Cache store; app default if `null`. Not used in `opcache` mode. |
| `query_cache.ttl` | `86400` (24h) | `LIGHTHOUSE_QUERY_CACHE_TTL` | Perf | Seconds; `null` = forever. |
| `validation_cache.enable` | `false` | `LIGHTHOUSE_VALIDATION_CACHE_ENABLE` | Perf/Exp | Caches query validation results. Off by default (#2603). |
| `validation_cache.store` | `null` | `LIGHTHOUSE_VALIDATION_CACHE_STORE` | Perf | |
| `validation_cache.ttl` | `86400` | `LIGHTHOUSE_VALIDATION_CACHE_TTL` | Perf | |
| `parse_source_location` | `true` | — | Perf | `false` drops `locations` from errors; slightly faster. |
| `namespaces.*` | `App\GraphQL\…` per type | — | Prod | Where Lighthouse discovers models, queries, mutations, types, directives, etc. |
| `security.max_query_complexity` | `DISABLED` (0) | — | Prod | `GraphQL\Validator\Rules\QueryComplexity`. |
| `security.max_query_depth` | `DISABLED` (0) | — | Prod | `QueryDepth`. |
| `security.disable_introspection` | `DISABLED` | `LIGHTHOUSE_SECURITY_DISABLE_INTROSPECTION` | Prod | Set env truthy to disable introspection. |
| `pagination.default_count` | `null` | — | Prod | Default page size; `null` = client must ask. |
| `pagination.max_count` | `null` | — | Prod | Max items per page; `null` = unrestricted. |
| `debug` | `INCLUDE_DEBUG_MESSAGE \| INCLUDE_TRACE` | `LIGHTHOUSE_DEBUG` | Prod | Bitmask 0–15; table in the config file. Only applies when Laravel `app.debug=true`. |
| `error_handlers` | Authentication, Authorization, Validation, Reporting | — | Prod | Pipeline; classes implement `Nuwave\Lighthouse\Execution\ErrorHandler`. |
| `field_middleware` | Trim, ConvertEmptyStringsToNull, Sanitize, Validate, TransformArgs, Spread, RenameArgs, DropArgs | — | Prod | **Order is significant** (`lighthouse-architecture-contract`). |
| `global_id_field` | `id` | — | Prod | Node interface global id field name. |
| `persisted_queries` | `true` | — | Prod | Automatic Persisted Queries (Apollo-compatible). |
| `transactional_mutations` | `true` | — | Prod | Wraps model mutations in a transaction committed after the root field; nested-field errors don't roll back. |
| `force_fill` | `true` | — | Prod | `forceFill()` over `fill()` in mutations (GraphQL constrains inputs). |
| `batchload_relations` | `true` | — | Prod | Batch-loads relations to fight N+1. Interacts with #2758/#2679. |
| `shortcut_foreign_key_selection` | `false` | — | Perf/Exp | Only works if every model's PK is exactly `id` (see config comment). Opt-in. |
| `subscriptions.queue_broadcasts` | `true` | `LIGHTHOUSE_QUEUE_BROADCASTS` | Prod | |
| `subscriptions.broadcasts_queue_name` | `null` | `LIGHTHOUSE_BROADCASTS_QUEUE_NAME` | Prod | |
| `subscriptions.storage` | `redis` | `LIGHTHOUSE_SUBSCRIPTION_STORAGE` | Prod | Cache driver for subscription storage. |
| `subscriptions.storage_ttl` | `null` | `LIGHTHOUSE_SUBSCRIPTION_STORAGE_TTL` | Prod | `null` = forever (may leak stale subs). |
| `subscriptions.encrypted_channels` | `false` | `LIGHTHOUSE_SUBSCRIPTION_ENCRYPTED` | Prod | |
| `subscriptions.broadcaster` | `pusher` | `LIGHTHOUSE_BROADCASTER` | Prod | `log`/`echo`/`pusher`/`reverb`. `reverb` uses the `pusher` driver. Use `log` to debug. |
| `subscriptions.exclude_empty` | `true` | `LIGHTHOUSE_SUBSCRIPTION_EXCLUDE_EMPTY` | Prod | |
| `defer.max_nested_fields` | `0` (unlimited) | — | **Exp** | `@defer` is explicitly experimental (config comment). |
| `defer.max_execution_ms` | `0` (unlimited) | — | **Exp** | |
| `federation.entities_resolver_namespace` | `App\GraphQL\Entities` | — | Prod | Apollo Federation `_entities` resolvers. |
| `tracing.driver` | `ApolloTracing::class` | — | Prod | **v7 default changes to `FederatedTracing`** (config comment). |

## Test-environment variables (NOT library config)

These configure the test DB/Redis, set in `phpunit.xml.dist` and overridden in `.github/workflows/validate.yml`. They are not part of the published config.

| Var | phpunit.xml.dist | validate.yml (CI) |
|---|---|---|
| `LIGHTHOUSE_TEST_DB_HOST` | `mysql` | `127.0.0.1` |
| `LIGHTHOUSE_TEST_DB_PORT` | `3306` | `33060` |
| `LIGHTHOUSE_TEST_DB_USERNAME` | `root` | `root` |
| `LIGHTHOUSE_TEST_DB_PASSWORD` | (empty) | `root` |
| `LIGHTHOUSE_TEST_DB_DATABASE` | `test` | `test` |
| `LIGHTHOUSE_TEST_REDIS_HOST` | `redis` | `127.0.0.1` |
| `LIGHTHOUSE_TEST_REDIS_PORT` | `6379` | `63790` |

## How to add a new config option (checklist)

1. **Add the key + a comment block** to `src/lighthouse.php`, matching the existing `/*---*/` banner style. Every option is documented inline.
2. **Choose a backward-compatible default.** The default must preserve current behavior for existing apps (change-control rule: no behavioral break in a minor). New behavior ships off/neutral by default (precedent: `validation_cache` off, `shortcut_foreign_key_selection` false).
3. **Decide on an `env()` wrapper.** Pattern in this codebase: deployment-facing toggles get an `env('LIGHTHOUSE_…')` fallback; structural options (namespaces, handler lists) do not.
4. **Read it via injected `ConfigRepository`** in `src/`, not a Facade.
5. **Add a test** that exercises both default and set values. Tests set config in `getEnvironmentSetUp($app)` via `$app->make(ConfigRepository::class)->set('lighthouse.…', …)` — see `tests/DBTestCase.php` and `tests/TestCase.php` for the pattern.
6. **Document** it in `docs/master/` where relevant and add a `CHANGELOG.md` `Added` entry ending with the PR URL.
7. If it changes generated schema or response shape → it may be breaking; run the checklist in `lighthouse-change-control`.

## Interactions and guards worth flagging

- `schema_cache.enable` default depends on `APP_ENV` — behaves differently in `local` vs prod.
- `cache_directive_tags=true` requires a **taggable** cache store (Redis/Memcached), not `file`/`array`.
- `query_cache.mode=opcache|hybrid` needs a writable `opcache_path` folder and OPcache enabled.
- `subscriptions.broadcaster=reverb` maps to the `pusher` driver with a `reverb` connection.
- `shortcut_foreign_key_selection=true` is only safe if **every** type's primary key field is literally `id`.
- `security.*` limits are all DISABLED by default — production hardening is opt-in.

## When NOT to use this skill

- Why a decision (laziness, transaction semantics) is shaped this way → `lighthouse-architecture-contract`.
- Diagnosing a flag-dependent bug → `lighthouse-debugging-playbook`.
- Domain meaning of a feature (APQ, federation, connections) → `graphql-laravel-reference`.
- Gating a config-default change as breaking → `lighthouse-change-control`.

## Provenance and maintenance

Verified 2026-07-07 against v6.68.0 (`master` @ a29ff8e). `php -r "require 'src/lighthouse.php'"` does **not** run standalone (it calls `base_path()`), so use grep-based drift checks:

```bash
grep -nE "^\s*'[a-z_]+' =>" src/lighthouse.php          # top-level + nested keys
grep -oE "env\('LIGHTHOUSE_[A-Z_]+'" src/lighthouse.php  # env var inventory
grep -n "vendor:publish\|publishes\|lighthouse-config" src/LighthouseServiceProvider.php
grep -rn "env(" src --include="*.php" | grep -v lighthouse.php   # env reads outside config (expect none)
```
`tracing.driver` default and `EnsureXHR`/test-trait behavior change at v7 — re-check `UPGRADE.md` after any major bump.
