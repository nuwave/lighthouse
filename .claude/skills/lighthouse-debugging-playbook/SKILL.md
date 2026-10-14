---
name: lighthouse-debugging-playbook
description: >-
  Triage a live Lighthouse failure fast. Load when a GraphQL request errors, a
  schema won't build, a directive "isn't found", results are stale after editing
  .graphql files, there are too many DB queries (N+1), subscriptions don't
  broadcast, errors are hidden or over-exposed, a request is rejected before
  execution, or a test passes locally but fails in CI. Provides a symptom→cause
  triage table, the time-wasting traps with their stories, and one-axis-at-a-time
  discriminating experiments. For turning a confirmed bug into a failing test, use
  lighthouse-issue-triage-and-reproduction instead.
---

# Lighthouse debugging playbook

Fast triage for the failure modes that actually occur in this project.
Every command below is the Docker+Make form (primary) with the native form as an alternative; commands were verified against `Makefile`, `composer.json`, `phpunit.xml.dist`, and source on 2026-07-07, **not** executed here (this authoring environment has no `vendor/`).

Key definitions used throughout: **directive** = a `@foo` annotation in the schema backed by a `FooDirective` PHP class. **AST** = the parsed schema document Lighthouse manipulates before building the executable schema. **N+1** = one query per parent row instead of one batched query for all of them.

## First 5 minutes (do this for every report)

1. Get the exact GraphQL query, variables, and the full error JSON (`errors[].message`, `errors[].extensions`).
2. Get versions: Lighthouse, `webonyx/graphql-php`, PHP, Laravel. Many bugs are version-boundary bugs (see #2771, #2679).
3. Reproduce in a test before theorizing — see `lighthouse-issue-triage-and-reproduction`. A red test beats a paragraph of speculation.
4. Check whether the schema is **cached**: `schema_cache.enable` defaults to `true` when `APP_ENV !== 'local'` (`src/lighthouse.php` ~line 93). A stale cache masquerades as "my change did nothing".
5. Turn on debug detail: set `LIGHTHOUSE_DEBUG` high (see the DebugFlag table in `src/lighthouse.php`, values 0–15) and ensure Laravel `app.debug = true`.

## Symptom → cause → first move

| Symptom | Likely cause | First move |
|---|---|---|
| `DefinitionException` / schema won't build | Invalid SDL, or directive manipulating AST wrongly | Read the message; it names the type/field. `src/Exceptions/DefinitionException.php`. Run `lighthouse:validate-schema`. |
| `SchemaSyntaxErrorException` | Malformed `.graphql` | Fix syntax at the reported location. `src/Exceptions/SchemaSyntaxErrorException.php`. |
| "Directive @foo not found" / wrong directive runs | Name→class resolution or namespace priority | `FooDirective` must exist; `DirectiveLocator::className()` = `Str::studly(name).'Directive'`. Check `lighthouse.namespaces.directives`; user namespaces win over Lighthouse's (`DirectiveLocator::namespaces()`). |
| Schema edits have no effect | Schema cache serving stale AST | Clear it: `lighthouse:clear-schema-cache`. In tests, the schema is refreshed via `RefreshesSchemaCache`. |
| Too many DB queries / slow list | Batch loading off, or relation not using a relation directive | Confirm `batchload_relations` (default `true`). Assert query count in a `DBTestCase` (`AssertsQueryCounts`). |
| Batched relation returns error under Laravel 12 | `automaticallyEagerLoadRelationships()` clashes with `RelationBatchLoader` | Known **OPEN #2679**. Workaround: don't enable Laravel auto eager loading with `batchload_relations`. |
| `@canResolved(action: RETURN_VALUE)` ignored | Authorization bypassed when batching relations | Known **OPEN #2758**. Confirm by toggling `batchload_relations=false`. |
| Subscriptions don't broadcast | Broadcaster/driver/storage misconfig | Switch `broadcaster` to `log` (`src/Subscriptions/Broadcasters/LogBroadcaster.php`) to see intended broadcasts in the log; isolates transport vs logic. |
| Errors hidden (no message/trace) | `debug` flag too low, or `app.debug=false` | Raise `LIGHTHOUSE_DEBUG`; debug only applies when Laravel debug is on (`src/lighthouse.php` comment). |
| Internal exception leaked to client | Error handler / debug flag exposing internals | Review `error_handlers` pipeline (`src/Execution/*ErrorHandler.php`) and debug flag; `ClientAware` errors are safe, others are masked unless RETHROW flags set. |
| Request rejected before any resolver runs | HTTP middleware | Inspect `lighthouse.route.middleware`: `AcceptJson`, `AttemptAuthentication`, `EnsureXHR` (`src/Http/Middleware/`). |
| GET or HTML-form POST blocked | `EnsureXHR` middleware | Commented **out** in v6 default config; **default-on in v7** (`UPGRADE.md` → "EnsureXHR is enabled"). If seen in v6, someone enabled it. |
| Passes locally, fails in CI | Matrix cell you didn't run | See "CI-only failures" below. |
| Fatal only under Lumen | Facade/helper used in `src/` | Lumen lacks Facades; `grep -rn "Facades" src` must be empty. Use DI. |

## Traps that cost real time (with their stories)

- **The schema cache trap.** Editing a `.graphql` file and seeing no change is almost always a stale schema cache, not a broken change. The cache defaults on outside `local`. In tests, `src/Testing/RefreshesSchemaCache.php` handles refresh (auto in v7). Always clear the cache before concluding your change "doesn't work".

- **The graphql-php version boundary.** Upstream bumps repeatedly cause subtle breakage. #2771: lazy schema silently becomes eager on `webonyx/graphql-php` ≥ 15.31 (huge latency/memory regression, no error thrown). #2679: Laravel 12's auto eager loading breaks batch loaders. Lesson: when perf or behavior changes with no Lighthouse code change, suspect the dependency version first; bisect by pinning constraints. Lighthouse guards such gaps with `method_exists` checks (real example: `src/GraphQL.php:166`, `// TODO remove this check when updating the required version of webonyx/graphql-php`).

- **Batch loading vs authorization/eager-loading.** `batchload_relations` changes how relations are fetched and currently interacts badly with `@canResolved` (#2758) and Laravel auto eager loading (#2679). When a relation-related bug appears, toggling `batchload_relations` is the fastest discriminator.

- **Nested mutation scope.** Nested mutations run unscoped queries (#2744, OPEN) — a mutation can hit records outside the parent relationship. If a user reports "my nested update touched the wrong record", it is likely this known issue, not their misuse. See `lighthouse-failure-archaeology`.

- **CI-only failures.** `validate.yml` runs the **lowest** dependency set (`--prefer-lowest --prefer-stable`) and old Laravel/PHP, and **removes** some dev deps in CI (`composer remove --dev ... larastan phpstan-mockery phpbench rector`; removes `laravel/pennant` on Laravel 9). So a green local run on highest deps proves nothing about the floor. Reproduce the failing cell's PHP+Laravel+lowest combo locally.

## Discriminating experiments (toggle ONE axis)

Change one variable, observe, revert. Each isolates a hypothesis.

| Hypothesis | Experiment |
|---|---|
| Stale schema cache | `docker compose run --rm php vendor/bin/artisan lighthouse:clear-schema-cache` (native: `php artisan …`) then retry |
| Batch loading is the cause | Set `lighthouse.batchload_relations=false`, retry; if fixed, it's a batch-loader interaction (#2679/#2758) |
| Subscription transport vs logic | Set `lighthouse.subscriptions.broadcaster=log`, retry, read the log |
| Errors are masked, not absent | Raise `LIGHTHOUSE_DEBUG` (e.g. 3 or 15) with `app.debug=true` |
| Schema output changed | `php artisan lighthouse:print-schema` before and after your change, `diff` |
| Isolate one test | `docker compose run --rm php vendor/bin/phpunit --filter=SomeTest` (native: `vendor/bin/phpunit --filter=SomeTest`) |
| Upstream vs Lighthouse regression | Pin `webonyx/graphql-php` to the prior version in `composer.json`, re-measure |

## When NOT to use this skill

- Turning a confirmed bug into a failing test / triaging an issue report → `lighthouse-issue-triage-and-reproduction`.
- A battle that's already settled (don't re-diagnose) → `lighthouse-failure-archaeology`.
- Measuring instead of guessing (query counts, benchmarks, tracing) → `lighthouse-diagnostics-and-tooling`.
- Proving a fix is correct/non-breaking → `lighthouse-proof-and-analysis-toolkit`.

## Provenance and maintenance

Verified 2026-07-07 against v6.68.0 (`master` @ a29ff8e). Re-verify with:

```bash
ls src/Exceptions/                                        # exception class inventory
grep -n "className\|studly" src/Schema/DirectiveLocator.php
grep -n "schema_cache\|batchload_relations\|debug" src/lighthouse.php
ls src/Subscriptions/Broadcasters/                        # broadcaster drivers (log/echo/pusher)
grep -rh "\$name = 'lighthouse:clear" src/Console/*.php    # clear commands
grep -n "method_exists" src/GraphQL.php                   # upstream version guard example
```
Issue statuses (#2771, #2679, #2758, #2744) were OPEN on 2026-07-07 — re-check on GitHub before relying on them.
