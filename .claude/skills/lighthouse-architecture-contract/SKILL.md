---
name: lighthouse-architecture-contract
description: >-
  Understand Lighthouse's load-bearing design decisions, the invariants that must
  hold, and the known-weak points before changing internals. Load when touching the
  schema build pipeline (ASTBuilder, SchemaBuilder, TypeRegistry, DirectiveLocator),
  the execution path (BatchLoader, ModelsLoader, Arguments), directive interfaces,
  service-provider registration, or anything whose correctness depends on laziness,
  serializability, transaction semantics, or Lumen/Laravel dual support. Explains
  WHY the system is shaped this way and what breaks if an invariant is violated.
---

# Lighthouse architecture contract

Lighthouse is a schema-first GraphQL framework for Laravel: you write GraphQL SDL, annotate it with server-side **directives**, and Lighthouse turns that into an executable schema backed by Eloquent.
This skill is the map of the load-bearing structure — the decisions you must not casually undo and the invariants you must not break.

Ground truth date: 2026-07-07, v6.68.0 (`master` @ a29ff8e). Every path and class below was read from source.

## System map: request lifecycle and owning classes

Reference doc: `docs/master/concepts/request-lifecycle.md`. The runtime path:

| Stage | Owner | Notes |
|---|---|---|
| Route registration | `src/Http/routes.php` → `src/Http/GraphQLController.php` | Single endpoint (`lighthouse.route`, default `/graphql`); works under Laravel Router or Lumen Router. |
| HTTP middleware | `src/Http/Middleware/` (`AcceptJson`, `AttemptAuthentication`, `EnsureXHR`, `LogGraphQLQueries`) | Configured in `lighthouse.route.middleware`. Runs before GraphQL execution. |
| Request parsing | `laragraph/utils` + `src/Http/` | Parses HTTP into GraphQL operation(s); follows the informal GraphQL-over-HTTP spec. |
| Schema construction | `src/Schema/Source/SchemaStitcher` → `src/Schema/AST/ASTBuilder.php` → `src/Schema/SchemaBuilder.php` → `src/Schema/TypeRegistry.php` | Cached (see schema cache). Directives manipulate the AST here. |
| Directive resolution | `src/Schema/DirectiveLocator.php` | Maps `@foo` ↔ `FooDirective` by namespace priority. |
| Query validation | `webonyx/graphql-php` `DocumentValidator` via `src/GraphQL.php` | Plus `security` rules (complexity/depth/introspection). Cacheable (`validation_cache`). |
| Field execution | `src/Execution/` + `src/Schema/Factories/FieldFactory.php` | Field middleware pipeline wraps resolvers; batch loading dedupes relation queries. |
| Error handling | `src/Execution/` error handlers (`error_handlers` config) + `ErrorPool` | Errors collected, subtree aborted, rest continues. |
| Response assembly | `src/GraphQL.php` (`@api` entrypoint) | Debug flags applied per `lighthouse.debug`. |

`src/GraphQL.php` is the `@api`-marked entrypoint (`executeQueryString`, `executeParsedQuery`, `executeOperationOrOperations`).

## Load-bearing decisions (and WHY)

1. **Schema-first SDL + directive-driven AST manipulation** (not code-first). Users declare intent in `.graphql`; directives rewrite the `DocumentAST` before the executable schema is built (`ASTBuilder`). Why: keeps the schema the single source of truth and makes behavior declarative. Consequence: most features are directives, and schema transformations happen once at build time, not per request.

2. **Directive naming convention + namespace discovery.** `FooDirective` ⇄ `@foo`. `DirectiveLocator::className()` does `Str::studly($name) . 'Directive'`; `directiveName()` is the inverse. Namespaces are tried in priority order (`config('lighthouse.namespaces.directives')` first, then Lighthouse's own — see `DirectiveLocator::namespaces()`), so user directives can override built-ins. Why: zero-registration extensibility.

3. **One service provider per optional feature.** `composer.json` → `extra.laravel.providers` lists ~11 providers (Auth, Cache, GlobalId, OrderBy, Pagination, SoftDeletes, Testing, Validation, Async, Bind, plus the core `LighthouseServiceProvider`). CacheControl, Federation, Pennant, Scout, Subscriptions register conditionally. Why: features stay decoupled and optional; a user who does not need Scout never loads it.

4. **Laziness is a design goal.** `SchemaBuilder::build()` sets `$config->setTypeLoader(fn ($name) => $this->typeRegistry->search($name))` so types are built on demand, not all upfront. This matters because the schema is rebuilt per request under PHP-FPM when the schema cache is cold or disabled. **Known breach:** `$config->setTypes(fn () => $this->typeRegistry->possibleTypes())` (needed for introspection) is a lazy callable, but graphql-php ≥ 15.31 resolves it on the first built-in scalar lookup, defeating laziness — issue **#2771, OPEN** (see Known-weak points).

5. **Field middleware pipeline order is config-defined and significant.** `lighthouse.field_middleware` lists directives that wrap every field, executed in array order: `TrimDirective`, `ConvertEmptyStringsToNullDirective`, `SanitizeDirective`, `ValidateDirective`, `TransformArgsDirective`, `SpreadDirective`, `RenameArgsDirective`, `DropArgsDirective`. Why order matters: sanitization must precede validation; `@spread` must flatten input before renames/drops act on it. Do not reorder without understanding each stage.

6. **Transactional mutations commit after the root field.** `lighthouse.transactional_mutations` (default `true`) wraps built-in model-mutating directives in a DB transaction that is committed **after the root field resolves** — so errors in **nested** fields do **not** roll back the root mutation (documented in the `src/lighthouse.php` comment). This is deliberate; changing it silently changes data-integrity semantics for every user.

7. **`force_fill` over `fill`.** `lighthouse.force_fill` (default `true`) bypasses Laravel mass-assignment protection in mutation directives, because GraphQL already constrains allowed inputs by schema. Why: mass-assignment guards are redundant and would reject valid GraphQL input.

8. **Lumen + Laravel dual support → no Facades, DI everywhere.** CONTRIBUTING.md forbids Facades (Lumen may not enable them) and prefers direct Illuminate classes over helpers. Sanctioned exception: the `response()` helper (DI of `ResponseFactory` does not work in Lumen). `src/Http/routes.php` resolves the router defensively for both frameworks.

9. **Extensibility contract:** `protected` over `private`, never `final` in `src/`, `@api` marks the stable surface (33 annotations). See `lighthouse-change-control` for the enforcement rules.

## Invariants (what must hold, and how to check)

| Invariant | Why it must hold | Breaks if violated | Check |
|---|---|---|---|
| Schema consumers never break within a major | Users `composer update` blindly within `^6` | Every downstream API breaks at once | `lighthouse:print-schema` diff = empty (`lighthouse-change-control`) |
| Types resolve lazily per request | Per-request schema rebuild must be cheap | Latency + memory blow up (see #2771) | `grep -n "setTypeLoader" src/Schema/SchemaBuilder.php` |
| Every directive has an SDL definition | Schema validation + IDE helper need it | Schema build throws / directive ignored | `src/Support/Contracts/Directive.php` requires `public static function definition(): string` |
| `DocumentAST` stays serializable | Schema cache serializes it to a PHP file | Schema cache write/read fails | `src/Schema/AST/DocumentAST.php`, `ASTCache` |
| Nested-field errors don't roll back the root mutation | Documented transaction semantics | Silent data-integrity change | `src/Execution/TransactionalMutations.php`; `src/lighthouse.php` `transactional_mutations` comment |
| No Facades in `src/` | Lumen compatibility | Lumen apps fatal | `grep -rn "Facade\|Illuminate\\\\Support\\\\Facades" src` should be empty |

## Known-weak points (stated plainly — all OPEN as of 2026-07-07)

- **#2771** — Lazy schema becomes eager on every request with `webonyx/graphql-php` ≥ 15.31. First built-in scalar lookup triggers scalar-override discovery, which resolves the `setTypes` callable → `TypeRegistry::possibleTypes()` builds every type. Measured 1.5–3.4× slower, ~6× PHP work. Fix depends on upstream `SchemaConfig::setScalarOverrides()`. This is the single biggest architectural liability right now.
- **#2758** — `batchload_relations` bypasses `@canResolved(action: RETURN_VALUE)`; authorization result ignored for batched relations.
- **#2679** — Laravel 12's `Model::automaticallyEagerLoadRelationships()` is incompatible with Lighthouse's `RelationBatchLoader` (`Collection::relationLoaded does not exist`).
- **#2744** — Nested mutations run unscoped queries (`$relation->getRelated()::destroy($ids)`, `$model->newQuery()->findOrFail($id)`), letting a mutation touch records outside the parent relationship. Fix is breaking → deferred to v7. See `lighthouse-failure-archaeology`.
- **MySQL 5.7 test pin** — `docker-compose.yml` pins `mysql:5.7` with `# TODO switch to MySQL 8` (issue #1784).
- **PHPStan level 8, not max** — `phpstan.neon` `# TODO level up to max`.

## When NOT to use this skill

- Rules for gating/versioning a change → `lighthouse-change-control`.
- Every config key and its default → `lighthouse-config-and-flags`.
- GraphQL/Eloquent domain concepts → `graphql-laravel-reference`.
- The history of how a decision was reached → `lighthouse-failure-archaeology`.
- Diagnosing a live failure → `lighthouse-debugging-playbook`.

## Provenance and maintenance

Verified 2026-07-07 against v6.68.0 (`master` @ a29ff8e). Re-verify with:

```bash
grep -n "setTypeLoader\|setTypes\|possibleTypes" src/Schema/SchemaBuilder.php
grep -n "className\|directiveName\|studly" src/Schema/DirectiveLocator.php
grep -rn "final " src/ | grep -v "finally"          # should be empty in src/
grep -n "transactional_mutations\|force_fill" src/lighthouse.php
sed -n '/extra/,/scripts/p' composer.json | grep -i provider   # service provider list
```
