---
name: lighthouse-failure-archaeology
description: >-
  The chronicle of settled battles — past investigations, dead ends, reverts, and
  the reasons behind them. Load BEFORE proposing a fix or refactor in schema caching,
  nested mutations, pagination-type generation, resolver-efficiency rewrites, directive
  additions, or graphql-php version handling, so you don't re-fight a decided battle or
  re-introduce a reverted change. Each entry is symptom → root cause → evidence
  (commit/PR/issue) → status → lesson. For diagnosing a NEW live failure use
  lighthouse-debugging-playbook; for the rules on making changes use lighthouse-change-control.
---

# Lighthouse failure archaeology

The purpose of this file: **nobody should re-fight a settled battle.**
Before you "fix" something in these areas, check whether it was already tried, reverted, or deliberately left alone.

Ground truth date: 2026-07-07, v6.68.0 (`master` @ a29ff8e). Every commit SHA and PR below was verified with `git show`/`git log` or a `CHANGELOG.md` grep. Full history (3,928 commits) is available.

Reading key: **Status** is one of *fixed in vX.Y* / *OPEN as of 2026-07-07* / *rejected/reverted*.

---

## Saga 1 — Schema caching (the longest-running effort)

Schema building (parse + AST manipulation) is expensive, so the final schema is cached to a PHP file. Making that cache correct and safe took years of incremental fixes.

- **Schema cache v1 removed.** Symptom: two cache mechanisms coexisted, confusing and redundant. Root cause: v2 (AST-based) superseded v1. Evidence: `19ec1d1a` "Remove schema cache v1 (#2321)", documented in `c743aef8` (#2322); trait `ClearsSchemaCache` removed (CHANGELOG). **Status: done.** Lesson: retire the old mechanism once the replacement is proven — don't carry both.

- **Non-atomic cache writes → corruption/races.** Symptom: partially-written schema/query cache files read by concurrent requests. Root cause: write-in-place is not atomic. Evidence: `d5ea91d6` "Use exclusive lock when writing the parsed schema to a file in `ASTCache`" (#2685); `f6b214fd` "Write schema cache to temporary file before atomically updating it (#2703)"; `2de0a54b` "Use atomic file writes for query cache (#2716)". **Status: fixed (v6.63.x).** Lesson: cache writes must be write-temp-then-rename (atomic on POSIX). See the proof recipe in `lighthouse-proof-and-analysis-toolkit`.

- **OPcache-backed query cache added, then hardened.** Symptom: parsing query strings on every request is slow. Root cause: no local cache leveraging OPcache. Evidence: `780da320` "Add a file based query cache to leverage OPcache for improved performance (#2713)" — which also deprecated `lighthouse:clear-cache` in favor of `lighthouse:clear-query-cache`/`lighthouse:clear-schema-cache`; hardened by #2716 (atomic writes) and `910a2184` "Make Automatic Persisted Queries work with hybrid cache mode (#2727)". **Status: shipped (v6.63.0+).** Lesson: a feature ships, a flaw is found, it's fixed forward — the follow-up PRs are part of the feature.

- **Validation cache.** Symptom: re-validating identical queries wastes time. Evidence: `bebb209b` "Cache query validation results" (#2603); config `validation_cache` (default **off**). **Status: shipped, opt-in.** Lesson: perf features that change behavior ship off-by-default first.

- **Octane memory exhaustion.** Symptom: with the schema cache enabled under Laravel Octane, memory climbs until fatal (`Allowed memory size … exhausted`). Root cause: unconfirmed (reporter suspects GC not running with cache on). Evidence: issue **#2436**, labeled *needs reproduction*. **Status: OPEN as of 2026-07-07.** Lesson: no conclusive minimal reproduction yet — see `lighthouse-proof-and-analysis-toolkit` for what a conclusive Octane memory repro must show.

- **Test-time schema cache refresh.** Symptom: cached schema leaks between tests / parallel test workers. Evidence: `318ad4d4` (#2076), `299a3690` (#1920), lazy refresh `03f3dab5`/#2702; trait `src/Testing/RefreshesSchemaCache.php`. In **v7** this setup becomes automatic (its `bootRefreshesSchemaCache()` is `@deprecated`; `UPGRADE.md` → "Leverage automatic test trait setup"). **Status: fixed; auto in v7.**

---

## Saga 2 — Nested mutation semantics (the most error-prone area)

Nested mutations (`create`/`update`/`upsert`/`delete`/`connect`/`disconnect`/`sync` on related models) live in `src/Execution/Arguments/Nested*.php`. Edge cases have bitten repeatedly.

- **Null handling reverted.** Symptom: a fix for nested operations receiving `null` caused regressions. Evidence: `c0eac85e` "Revert 'Fix handling of nested mutation operations that receive `null`'" — touched `NestedBelongsTo`, `NestedManyToMany`, `NestedMorphTo`, `NestedOneToMany`. **Status: reverted.** Lesson: null semantics in nested mutations are subtle; a fix that passes its own test can break others — demand adversarial testing across all Nested* handlers.

- **Flatten-in-create/update reverted in favor of `@spread`.** Symptom: attempt to auto-flatten nested input. Evidence: `a6ae1e29` "Revert trying to properly flatten in create/update, @spread is preferred". **Status: rejected** — the sanctioned mechanism is the `@spread` directive, not implicit flattening. Lesson: don't re-add implicit flattening.

- **HasOne upsert vs create.** Symptom: `upsert` on a `HasOne` created a new record instead of updating when no `id` given; `create` on a `HasOne` with an existing related record didn't error. Evidence: CHANGELOG v6.64.3, PR #2742. **Status: fixed (v6.64.3).**

- **Upsert identifying columns.** Evidence: "Specify identifying columns on nested mutation upserts with `@upsert`/`@upsertMany`", PR #2426 (v6.65.0). **Status: shipped.**

- **Pre/post-save timing for nested arg resolvers.** Symptom: nested arg resolvers needed to run before the parent model is saved. Evidence: `961aecf` "Allow nested arg resolvers to run before parent model is saved (#2777)" — added the `SaveAwareArgResolver` interface (v6.68.0). **Status: shipped (v6.68.0).**

- **Unscoped nested operations (security).** Symptom: a nested `update`/`delete`/`connect`/etc. can affect records **outside** the parent relationship (e.g. update another user's task by id). Root cause: unscoped queries — `$relation->getRelated()::destroy($ids)`, `$model->newQuery()->findOrFail($id)`. Evidence: issue **#2744** (supersedes #1400, which reported only the HasMany delete case). The behavior is documented under "Security considerations" in `docs/master/eloquent/nested-mutations.md`, but is accepted as a real issue. Fix (scope through the relation) is **breaking** → deferred to v7 (options: major default / opt-in flag / new directive). **Status: OPEN as of 2026-07-07, accepted-as-real, unfixed.** Lesson: this is settled as a real problem — don't re-litigate whether it's a bug; the open question is only *how* to fix without breaking (see `lighthouse-v7-campaign`).

---

## Reverts and dead ends (shorter)

- **`@cacheControl` — reverted, then re-added.** First added (`f4439f46`), reverted (`315392fd` "Revert 'Add `@cacheControl` directive'"), then successfully re-added via `59187c79` (#2136). It now lives in `src/CacheControl/`. **Status: shipped.** Lesson: a revert is not always permanent — the second attempt landed. Don't assume "reverted" = "forbidden forever"; check current `src/`.

- **`@namespaced` — reverted, then re-added.** Reverted in `198afbff`, later re-added (`90950d1a`, #2478). Both `src/Schema/Directives/NamespacedDirective.php` and `NamespaceDirective.php` exist today and it's documented (`docs/master/api-reference/directives.md`). **Status: shipped.** Lesson: same as above — verify against current source, not just the revert commit.

- **"Resolve fields more efficiently (#961)" reverted.** A performance rewrite of `src/Schema/TypeRegistry.php` was reverted: `7cfd7922` "Revert 'Resolve fields more efficiently (#961)'" (12 insertions / 13 deletions restored). **Status: rejected.** Lesson: resolver-efficiency rewrites of `TypeRegistry` have failed before — treat that hot path with extreme caution and demand benchmarks + full behavioral tests (see `lighthouse-research-frontier` item 1).

- **Pagination schema-type change reverted.** `88d8e18f` "Revert breaking schema change in generate pagination types (#2104)" — altered generated pagination types in the printed schema, broke consumers. Also see `790` CHANGELOG entry "Prevent regression to simple paginator type on fields using `@cache` (#2354/#2355)". **Status: reverted / fenced with regression test.** Lesson: any change to generated schema types is breaking. This is the canonical incident behind the "no breaking changes for schema consumers" rule in `lighthouse-change-control`.

- **`IdeHelperCommandTest` suite move reverted.** `107504ee` "Revert 'Move slow IdeHelperCommandTest to integration suite'". Minor; recorded so it isn't retried blindly.

---

## graphql-php upstream friction

- **Lazy schema eager on ≥ 15.31 (#2771, OPEN).** Full mechanism and measured impact in `lighthouse-architecture-contract` and the campaign skill. Fix awaits upstream `SchemaConfig::setScalarOverrides()`.
- **The version-guard pattern.** When behavior depends on the installed graphql-php version, Lighthouse guards with `method_exists`. Real example: `src/GraphQL.php:166` — `method_exists($queryComplexityRule, 'getQueryComplexity')` with `// TODO remove this check when updating the required version of webonyx/graphql-php`. Introduced alongside #2637. #2771's proposed fix follows the same pattern. Lesson: don't bump the graphql-php floor to clear a TODO in a minor (breaking for users on older 15.x) — guard instead.

## How to extend this chronicle

```bash
git log --oneline -i --grep="revert"           # find reverts
git show <sha> --stat                           # what a commit touched
git log --oneline -- src/<area>                 # history of a subsystem
grep -n "<keyword>" CHANGELOG.md                # released-change record (v3 → v6.68)
```
When you resolve or reject something significant, add an entry here with its evidence.

## When NOT to use this skill

- Diagnosing a NEW, unclassified live failure → `lighthouse-debugging-playbook`.
- The rules/gates for making a change → `lighthouse-change-control`.
- Planning the v7 breaking changes → `lighthouse-v7-campaign`.
- Turning a report into a failing test → `lighthouse-issue-triage-and-reproduction`.

## Provenance and maintenance

Verified 2026-07-07 against v6.68.0 (`master` @ a29ff8e). Re-verify with:

```bash
git show 88d8e18f --stat        # pagination schema revert
git show 7cfd7922 --stat        # #961 resolver-efficiency revert
git show c0eac85e --stat        # nested null-handling revert
git log --oneline -- src/CacheControl | tail        # @cacheControl revert+re-add
ls src/Schema/Directives/ | grep -i namespac        # @namespaced re-added
grep -n "2744\|2436\|2771" CHANGELOG.md             # confirm still unreleased/open
```
Open issues (#2744, #2436, #2771, #2758, #2679) were OPEN on 2026-07-07 — re-check GitHub.
