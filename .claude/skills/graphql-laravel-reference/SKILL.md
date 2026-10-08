---
name: graphql-laravel-reference
description: >-
  Domain-theory pack for engineers who know PHP/Laravel but not GraphQL internals,
  or vice versa. Load when a task needs the meaning of GraphQL/graphql-php/Eloquent
  concepts AS USED IN THIS REPO — SDL and directive definitions, executable schema
  and introspection, the type-loader vs types callable (laziness), DebugFlag,
  Eloquent-relation-to-directive mapping, batch loading, pagination types
  (PAGINATOR/SIMPLE/CONNECTION), Relay/Federation/APQ/tracing/@defer, or the jargon
  glossary. Anchors every concept to a file path. For design rationale use
  lighthouse-architecture-contract; for config values use lighthouse-config-and-flags.
---

# GraphQL + Laravel reference (as applied in Lighthouse)

The knowledge a mid-level engineer or smaller model is missing to work here. Not a textbook — every concept points at a file in this repo.

Ground truth date: 2026-07-07, v6.68.0 (`master` @ a29ff8e).

## 1. GraphQL for this repo

**SDL (Schema Definition Language)** — the text format describing types/fields. Lighthouse is *schema-first*: you write SDL, it builds the runtime. Example: `src/default-schema.graphql`:
```graphql
type Query {
    user(id: ID @eq): User @find
    users(name: String @where(operator: "like")): [User!]! @paginate(defaultCount: 10)
}
```

**Directive** — a `@foo` annotation attached to schema elements that drives server behavior. Every directive is a PHP class whose `definition(): string` returns its own SDL. Real example (`src/Schema/Directives/FieldDirective.php`):
```graphql
directive @field(resolver: String!) on FIELD_DEFINITION
```
`@field(resolver: "Class@method")` assigns a resolver; method defaults to `__invoke`. The SDL `on FIELD_DEFINITION` part is a **type-system directive location** — where the directive may legally appear. (Contrast **executable directives** like `@include`/`@skip` that appear in client queries.)

**Executable schema** — the in-memory `GraphQL\Type\Schema` object produced from the SDL, used to validate and execute queries. Built by `src/Schema/SchemaBuilder.php`.

**Introspection** — the built-in query API (`__schema`, `__type`) that lets clients discover the schema. It needs the full type list, which is why `SchemaBuilder` registers a `types` callable (`$config->setTypes(...)`). That callable's eager resolution is the crux of #2771.

**GraphQL over HTTP** — Lighthouse serves one endpoint (default `/graphql`), accepting POST (and GET), following the informal spec at graphql.org. Errors come back in the spec shape `{ "errors": [{ "message", "extensions", "locations", "path" }], "data": … }`.

**Operations / variables / fragments** — a request has an operation (query/mutation/subscription), optional named variables, and reusable fragments. In tests these appear as `/** @lang GraphQL */` nowdocs (see `lighthouse-testing-and-qa`).

## 2. webonyx/graphql-php essentials used here

- **`Schema` + `SchemaConfig`** — the config carries `typeLoader` (lazy, per-name: `fn ($name) => $typeRegistry->search($name)`) and `types` (eager list for introspection: `fn () => $typeRegistry->possibleTypes()`). The split of these two is Lighthouse's laziness mechanism; #2771 is the failure of that split under graphql-php ≥ 15.31.
- **`DocumentNode` / AST** — the parsed SDL. Lighthouse manipulates the AST (`src/Schema/AST/ASTBuilder.php`) — expanding `@paginate`, `@orderBy`, etc. — *before* building the executable schema, so transformations happen once and are cacheable.
- **`DebugFlag`** — a bitmask (`INCLUDE_DEBUG_MESSAGE`, `INCLUDE_TRACE`, `RETHROW_*`) controlling error verbosity; mapped to integers 0–15 in `src/lighthouse.php` `debug`. Only applied when Laravel `app.debug` is true.
- **Validation rules** — `QueryComplexity`, `QueryDepth`, `DisableIntrospection` (config `security`, all DISABLED by default).
- **Default field resolver** — graphql-php's fallback resolver reads array keys / object properties / `offsetExists`; Lighthouse overrides resolution via directives and `FieldValue`.
- **`ClientAware` errors** — graphql-php interface marking errors safe to show clients. Lighthouse's client-safe exceptions implement it: `src/Exceptions/DefinitionException.php`, `DirectiveException.php`, `RateLimitException.php`, `ClientSafeModelNotFoundException.php`, `SchemaSyntaxErrorException.php`. Non-`ClientAware` exceptions are masked unless a RETHROW debug flag is set.

## 3. Eloquent / Laravel mapped to Lighthouse

**Relations → directives** (all in `src/Schema/Directives/`): `@hasMany`, `@hasOne`, `@hasManyThrough`, `@hasOneThrough`, `@belongsTo`, `@belongsToMany`, `@morphOne`, `@morphMany`, `@morphTo`, `@morphToMany`. Each maps a GraphQL field to the corresponding Eloquent relation.

**Batch loading** — resolving a relation for a list of parents naively causes **N+1** (one query per parent). Lighthouse's `RelationBatchLoader` (`src/Execution/BatchLoader/`) collects all parents and issues one query per relation, then distributes results. Controlled by `batchload_relations` (default on). Model loaders in `src/Execution/ModelsLoader/` (Simple/Paginated/Count/Aggregate) implement the strategies.

**Pagination types** (`src/Pagination/PaginationType.php`): `PAGINATOR` (page-number based, with `paginatorInfo`), `SIMPLE` (has-more without total count), `CONNECTION` (Relay cursor-based). `@paginate(type: …)` selects one. Changing the generated pagination types in the schema is a classic breaking change (see #2104 in `lighthouse-failure-archaeology`).

**Mass assignment & `force_fill`** — Lighthouse uses `forceFill()` in mutations because GraphQL's schema already constrains inputs, making Laravel's `$fillable`/`$guarded` redundant (`force_fill` config, default on).

**Policies / gates → `@can*` family** (`src/Auth/`): `@canModel`, `@canFind`, `@canQuery`, `@canResolved`, `@canRoot` (the old single `@can` is `@deprecated` for v7). These call Laravel Gate/Policy checks at the field level.

**Validation → `@rules`/Validators** (`src/Validation/`): map Laravel validation rules onto arguments/inputs; custom Validator classes live under the `validators` namespace.

**Queues → subscription broadcasts** — subscription broadcasts can be queued (`subscriptions.queue_broadcasts`).

**Laravel vs Lumen** — Lighthouse supports both; hence no Facades in `src/` and DI everywhere (see `lighthouse-architecture-contract`).

## 4. Ecosystem protocols as implemented

- **Relay** — Node interface + global object IDs (`src/GlobalId/`), and cursor **connections** (`src/Pagination/ConnectionField.php`, `PaginationType::CONNECTION`).
- **Apollo Federation** — Lighthouse can act as a subgraph: `src/Federation/` provides entity resolution (`EntityResolverProvider`, `BatchedEntityResolver`), the federated schema printer (`FederationPrinter`), and `_entities`/`_service` support. Open asks: #2582, #2482.
- **Automatic Persisted Queries (APQ)** — clients send a query hash; the server caches the full query. Handled in `src/GraphQL.php` (`persisted_queries` config, default on; Apollo-compatible).
- **Tracing** — `src/Tracing/`: `ApolloTracing` (JSON, current default) and `FederatedTracing` (protobuf `reports.proto`, v7 default).
- **Incremental delivery (`@defer`)** — `src/Defer/` (`DeferrableDirective`), experimental per the `defer` config comment; streams deferred fields after the initial response.

## 5. Glossary

| Term | One-line meaning |
|---|---|
| SDL | GraphQL's schema text format. |
| AST | Parsed tree of the SDL/query that Lighthouse manipulates. |
| Directive | `@foo` annotation → `FooDirective` class driving behavior. |
| Resolver | Function producing a field's value. |
| Directive locator | Maps directive names ↔ classes by namespace (`DirectiveLocator`). |
| Type loader | Lazy per-name type factory (`SchemaConfig::setTypeLoader`). |
| Batch loader | Dedupes relation queries across a result set to kill N+1. |
| N+1 | One query per parent row instead of one batched query. |
| Introspection | Built-in schema-discovery query API. |
| Connection / cursor | Relay pagination shape / opaque position pointer. |
| Paginator | Page-number pagination with total count. |
| APQ | Automatic Persisted Queries (send hash, cache full query). |
| Federation / subgraph | Composing multiple GraphQL services; Lighthouse is a subgraph. |
| Field middleware | Directives wrapping every field's resolution, ordered. |
| ClientAware | graphql-php marker for errors safe to expose to clients. |
| DebugFlag | Bitmask controlling error verbosity. |

## When NOT to use this skill

- WHY the system is built this way / invariants → `lighthouse-architecture-contract`.
- Config keys and defaults → `lighthouse-config-and-flags`.
- Diagnosing a live failure → `lighthouse-debugging-playbook`.
- Writing tests that exercise these features → `lighthouse-testing-and-qa`.

## Provenance and maintenance

Verified 2026-07-07 against v6.68.0 (`master` @ a29ff8e). Re-verify with:

```bash
grep -n "PAGINATOR\|SIMPLE\|CONNECTION" src/Pagination/PaginationType.php
ls src/Schema/Directives/ | grep -iE "hasmany|belongsto|morph"
ls src/Federation/ src/Defer/ src/GlobalId/ src/Tracing/
grep -rln "ClientAware" src/Exceptions/
grep -n "setTypeLoader\|setTypes" src/Schema/SchemaBuilder.php
```
