---
name: lighthouse-research-frontier
description: >-
  Open problems where Lighthouse could advance the state of the art, framed as
  falsifiable projects. Load when scoping ambitious/long-horizon work beyond the next
  release: for each frontier item, why current SOTA falls short, Lighthouse's specific
  asset, the first three concrete steps in THIS repo, and a "you have a result when…"
  milestone. Every item is a CANDIDATE pending maintainer ratification. For the actual
  v7 release use lighthouse-v7-campaign; for day-to-day changes use
  lighthouse-change-control.
---

# Lighthouse research frontier

Where could Lighthouse go beyond the current state of the art? This skill frames the open bets as falsifiable projects, not aspirations.

**Important:** the maintainer's own definition of "beyond state of the art" was not captured (the intake question could not be delivered). Every item below is a **CANDIDATE** framing derived from the repo's open issues and architecture — treat it as a proposal to ratify, not a settled roadmap. Do not present these as the maintainer's stated goals.

Ground truth date: 2026-07-07, v6.68.0 (`master` @ a29ff8e). Repo facts verified; issue statuses OPEN on that date.

## 1. Zero per-request schema work under PHP-FPM (CANDIDATE)

- **Why SOTA falls short:** graphql-php's lazy type loading is defeated in Lighthouse by the `types` callable resolving on the first scalar lookup (#2771). Every request rebuilds the whole type set when the schema cache is cold.
- **Lighthouse's asset:** an existing OPcache-backed query cache (#2713) and AST schema cache (`src/Schema/AST/ASTCache.php`) prove the OPcache-native caching approach; #2771 provides a documented ablation baseline (`setTypes(fn () => [])`).
- **First three steps in this repo:** (a) land the `setScalarOverrides` fix behind a `method_exists` guard once upstream ships it; (b) add a regression test asserting `TypeRegistry::possibleTypes()` is **not** invoked on a simple execution; (c) prototype an OPcache-native cache of the fully-built schema, measured against the ablation baseline.
- **Result when:** an authenticated `{ __typename }` allocates within a small, stated % of the `setTypes(fn () => [])` ablation baseline, measured per `lighthouse-proof-and-analysis-toolkit` recipe 1.
- **Known wrong path:** resolver-efficiency rewrites of `TypeRegistry` have been reverted before (#961, `7cfd7922`) — demand benchmarks + full behavioral tests.

## 2. Secure-by-default nested mutations (CANDIDATE)

- **Why SOTA falls short:** major GraphQL mutation frameworks (including Lighthouse today) leave relation scoping to the developer; nested operations can touch records outside the parent (#2744).
- **Lighthouse's asset:** #2744 already contains a complete scope-of-impact table and a proposed fix (scope every id-based operation through the relation).
- **First three steps:** (a) write characterization tests of current nested-mutation behavior alongside the existing ones in `tests/Integration/Execution/MutationExecutor/` and `tests/Integration/Schema/Directives/`; (b) implement relation-scoped queries behind an opt-in flag; (c) propose default-on for v7 (see `lighthouse-v7-campaign` item 7).
- **Result when:** the #2744 exploitation example fails with an authorization/scope error **and** every existing nested-mutation test still passes.
- **Known wrong path:** shipping the scoping default-on in a v6 minor (breaking) — v7 or opt-in only.

## 3. First-class long-lived runtimes (Octane/Reverb era) (CANDIDATE)

- **Why SOTA falls short:** Lighthouse's design assumes per-request lifecycle; long-lived workers expose state-retention and memory issues (#2436 memory; `phpstan.neon` has `checkOctaneCompatibility` commented out).
- **Lighthouse's asset:** singletons are registered in `LighthouseServiceProvider`; the state-holding ones (e.g. request-scoped registries/pools) are enumerable — audit which hold request state across requests. (Verify the actual list before claiming; don't assume.)
- **First three steps:** (a) build the loop-request memory harness (recipe 7); (b) enable `checkOctaneCompatibility` in a dedicated PHPStan run and triage findings; (c) audit singleton lifetimes for request-state leakage.
- **Result when:** steady-state RSS is flat (within a stated bound) over N identical requests in one worker, cache enabled — resolving #2436.

## 4. Incremental delivery per spec (CANDIDATE)

- **Why SOTA falls short:** `@defer`/`@stream` are still stabilizing across the GraphQL ecosystem; Lighthouse's `@defer` is experimental (`src/Defer/`, config comment).
- **Lighthouse's asset:** a working experimental `@defer` and a streaming test helper (`streamGraphQL`).
- **First three steps:** (a) inventory current `@defer` behavior vs the incremental-delivery-over-HTTP draft; (b) add conformance tests; (c) decide on `@stream`.
- **Result when:** a conformance test suite for the incremental-delivery draft passes.

## 5. Federation completeness (CANDIDATE)

- **Why SOTA falls short:** federation moves fast; open asks exist (#2582 reserved-keyword entity types, #2482 context in entity resolvers).
- **Lighthouse's asset:** `src/Federation/` already provides entity resolution, a federated schema printer, and `_entities`/`_service`.
- **First three steps:** (a) map current `src/Federation/` capabilities against the federation spec version targeted; (b) close #2582/#2482; (c) add a compatibility test harness (the Apollo federation-compatibility suite is a candidate harness — confirm availability before committing to it).
- **Result when:** the chosen federation-compatibility suite passes.

## 6. Expressive-but-safe query building (CANDIDATE)

- **Why SOTA falls short:** richer `@whereConditions`/`@orderBy` (enum columns #2723, null-ordering #2486, aggregate/column validation #2487) risk expanding the SQL-injection / query-complexity surface.
- **Lighthouse's asset:** `src/WhereConditions/` and `src/OrderBy/` already constrain conditions to schema-defined columns.
- **First three steps:** (a) prototype enum-typed condition columns (#2723); (b) prove the enum eliminates a class of invalid queries with tests; (c) add complexity/safety bounds.
- **Result when:** enum-typed condition columns make an invalid-column query a schema/validation error rather than a runtime/SQL error.
- **Known wrong path:** the `@cacheControl` revert (`315392fd`) shows caching/expressiveness features can ship broken — land behind flags and prove semantics first.

## When NOT to use this skill

- The concrete next release → `lighthouse-v7-campaign`.
- Making any change today → `lighthouse-change-control`.
- Proving a result → `lighthouse-proof-and-analysis-toolkit`.
- The discipline for turning a bet into an accepted change → `lighthouse-research-methodology`.

## Provenance and maintenance

Verified 2026-07-07 against v6.68.0 (`master` @ a29ff8e). All items are CANDIDATE pending maintainer ratification. Re-verify with:

```bash
ls src/Defer/ src/Federation/ src/WhereConditions/ src/OrderBy/
grep -n "checkOctaneCompatibility" phpstan.neon
grep -n "possibleTypes\|setTypes" src/Schema/SchemaBuilder.php
ls tests/Integration/Execution/MutationExecutor/
```
Referenced issues (#2771, #2744, #2436, #2582, #2482, #2723, #2486, #2487) were OPEN on 2026-07-07 — re-check GitHub before investing.
