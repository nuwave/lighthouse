---
name: lighthouse-proof-and-analysis-toolkit
description: >-
  First-principles methods to PROVE a claim about Lighthouse instead of asserting it.
  Load when you must demonstrate — with evidence, not vibes — that schema building is
  lazy, that an N+1 is present or absent, that a change is backward compatible in
  schema or response shape, that a race/concurrency fix is sound, that a performance
  or memory claim holds, or that a bug is upstream (graphql-php) vs local. Each method
  is a recipe with a worked example from this repo's history. For the tools themselves
  use lighthouse-diagnostics-and-tooling; for the evidence bar and idea lifecycle use
  lighthouse-research-methodology.
---

# Lighthouse proof and analysis toolkit

"Prove it, don't just install it." This skill turns tools into airtight evidence. Each recipe: **Goal → Method → Commands/code → Worked example → Pitfalls.**

Ground truth date: 2026-07-07, v6.68.0 (`master` @ a29ff8e). Historical citations verified via `git show`/`CHANGELOG.md`.

The meta-rule (applies to every recipe): **one mechanism must explain ALL observations, including the negative ones.** If an observation doesn't fit your mechanism, the proof isn't done. See `lighthouse-research-methodology`.

## 1. Prove laziness (or eagerness) of schema building

- **Goal:** show whether building the executable schema builds all types (eager) or only what's needed (lazy).
- **Method:** spy on `TypeRegistry::possibleTypes()` vs `search()`; run a minimal operation; then **ablate** — replace the `types` callable with `fn () => []` and re-measure. If cost collapses, the `types` callable was the driver.
- **Commands/code:** profile a minimal op (`{ __typename }`) with Xdebug cachegrind on two library versions; compare cachegrind size / instruction count; patch `SchemaBuilder::build()`'s `setTypes(...)` to `fn () => []` for the ablation run.
- **Worked example (#2771, OPEN):** on `webonyx/graphql-php` ≥ 15.31, the first built-in scalar lookup resolves the `setTypes` callable → `possibleTypes()` builds every type. Measured 9.1 MB → 57 MB cachegrind for an authenticated `{ __typename }`, 1.5–3.4× wall clock. The ablation `setTypes(fn () => [])` restored the 15.30.2 baseline — proving the `types` callable is the cause, not "graphql-php got slower generally."
- **Pitfalls:** measure the *same* operation; disable the schema cache (a warm cache hides the rebuild cost you're studying); one op at a time.

## 2. Prove presence/absence of N+1

- **Goal:** show a relation is (or isn't) batch-loaded.
- **Method:** `DBTestCase` + `assertQueryCountMatches($n, fn () => …)` around the operation, with a dataset of **more than one** parent row.
- **Worked example:** `tests/Integration/Execution/DataLoader/RelationBatchLoaderTest.php` parametrizes `userCount`/`tasksPerUser` and asserts an exact count, precisely so batching is distinguishable from N+1.
- **Pitfalls:** with one parent row, N+1 and batched both issue the same number of queries — the test proves nothing. Always use ≥ 2 parents.

## 3. Prove schema backward compatibility

- **Goal:** show a change does not break schema consumers.
- **Method:** `lighthouse:print-schema --sort` before and after (or across two git refs via `scripts/schema-diff.sh`); diff.
- **What counts as breaking:** removed field/type/enum value, tightened nullability (`String` → `String!` on output is safe; `String!` → `String` on an input arg is safe; the reverse breaks), changed argument defaults, changed directive locations. **Safe/additive:** new nullable field, new type, new optional argument.
- **Worked example:** #2104 (`88d8e18f`) altered generated pagination types in the printed schema and had to be reverted — the print-schema diff is exactly what would have caught it pre-merge.
- **Pitfalls:** without `--sort`, ordering noise masks real diffs. Clear the schema cache between runs.

## 4. Prove response-shape backward compatibility

- **Goal:** show existing queries return the same JSON structure.
- **Method:** characterization test. Use `assertExactJson([...])` when the whole shape must be frozen; `assertJson([...])` when you only assert a subset. Both are available on the `TestResponse`.
- **Pitfalls:** `assertJson` passes even if extra keys appear — use it when additive keys are acceptable; use `assertExactJson` to catch any shape drift.

## 5. Prove a race / concurrency fix

- **Goal:** show a cache write can't be observed half-written by a concurrent request.
- **Method:** reason from atomicity — write to a temp file then `rename()` (atomic on POSIX: a reader sees either the old or the new file, never a partial one). Demonstrate the pre-fix race by simulating concurrent writers/readers.
- **Worked example:** `f6b214fd` "Write schema cache to temporary file before atomically updating it (#2703)" and `2de0a54b` "Use atomic file writes for query cache (#2716)"; `d5ea91d6` adds an exclusive lock in `ASTCache`. Before: in-place writes let a concurrent request read a truncated cache file. After: temp+rename makes the swap atomic.
- **Pitfalls:** `rename()` is only atomic within the same filesystem; a temp file on a different mount defeats it.

## 6. Prove a performance claim

- **Goal:** show a change is actually faster (or not slower).
- **Method:** PHPBench baseline on unchanged code, N iterations, then the change, then compare the aggregate report. Gate on **rstdev** — a delta smaller than the relative standard deviation is noise. Ablate to isolate cause. Never compare across machines or PHP versions.
- **Worked example:** the `benchmarks/` suite — `QueryBench`, `HugeResponseBench`, `HugeRequestBench`, `ASTUnserializationBench` (each `@Warmup(1) @Revs(10) @Iterations(10)`). `ASTUnserializationBench` answers "did schema-cache unserialize get slower?"; `HugeRequestBench` answers "did parsing regress?". Use `bench-report.sh before` / `... after`.
- **Pitfalls:** noisy CI runners; single-iteration measurements; comparing highest-deps to lowest-deps runs.

## 7. Prove memory behavior in long-lived processes

- **Goal:** distinguish a true leak (monotonic growth) from a high watermark (bounded).
- **Method:** loop the same operation many times in one process (Octane-style), sample `memory_get_usage(true)` each iteration, plot. Flat-after-warmup = watermark; monotonic climb = leak.
- **Worked example (#2436, OPEN):** reporter shows memory climbing to OOM with the schema cache enabled under Octane. A conclusive repro must add: the per-iteration memory series (to show monotonicity), and an ablation (cache off vs on) isolating the cache as the driver. Until that exists, the root cause is unproven — the issue is correctly labeled *needs reproduction*.
- **Pitfalls:** GC timing makes a few samples misleading; sample many iterations; force GC to separate "not collected yet" from "cannot be collected."

## 8. Prove a bug is upstream (graphql-php) vs Lighthouse

- **Goal:** locate the fault boundary.
- **Method:** build a minimal repro against **bare** `webonyx/graphql-php` (no Lighthouse). If it reproduces, it's upstream. Bisect by pinning graphql-php constraints to find the version boundary. Fix pattern: report/PR upstream, guard locally with `method_exists` until the constraint can be bumped.
- **Worked example:** #2771 identifies the boundary at graphql-php 15.30.2 → 15.31 and proposes an upstream API (`setScalarOverrides`) plus a local `method_exists` guard. Precedent for the guard: `src/GraphQL.php:166` (`method_exists($queryComplexityRule, 'getQueryComplexity')`, `// TODO remove this check when updating the required version of webonyx/graphql-php`), introduced with #2637.
- **Pitfalls:** don't bump the graphql-php floor to "fix" it in a minor — that's breaking for users on older 15.x (see `lighthouse-v7-campaign`).

## When NOT to use this skill

- The tools/commands themselves → `lighthouse-diagnostics-and-tooling`.
- The evidence bar, predict-first discipline, idea lifecycle → `lighthouse-research-methodology`.
- What acceptance requires for a normal change → `lighthouse-testing-and-qa`.
- Whether a proven change is breaking → `lighthouse-change-control`.

## Provenance and maintenance

Verified 2026-07-07 against v6.68.0 (`master` @ a29ff8e). Re-verify with:

```bash
git show f6b214fd --stat ; git show 2de0a54b --stat      # atomic cache writes
grep -n "assertQueryCountMatches" tests/Integration/Execution/DataLoader/RelationBatchLoaderTest.php
grep -n "setTypes\|possibleTypes" src/Schema/SchemaBuilder.php
grep -n "method_exists" src/GraphQL.php
ls benchmarks/
```
Issues #2771 and #2436 were OPEN on 2026-07-07.
