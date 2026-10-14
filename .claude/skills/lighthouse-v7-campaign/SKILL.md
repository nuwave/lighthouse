---
name: lighthouse-v7-campaign
description: >-
  The executable, decision-gated campaign for shipping Lighthouse v7 (the current
  hardest live problem). Load when planning, scoping, or executing the next major
  release: what breaking changes are already committed, what candidates are on the
  table (nested-mutation scoping #2744, graphql-php constraint bump, support-matrix
  drops), the per-change proof obligations, the release gates with measurable pass
  criteria, and the wrong paths that are fenced off. Routes all promotion through
  lighthouse-change-control. This is the most volatile skill — re-verify its
  inventory before acting.
---

# Lighthouse v7 campaign

Goal: ship a correct v7 major with every breaking change justified, proven non-accidental, and documented in `UPGRADE.md`.
Success is measurable (green matrix, traced schema diff, complete changelog), never judged by eye.
This skill does not authorize anything — every promotion routes through `lighthouse-change-control`.

Ground truth date: 2026-07-07, v6.68.0 (`master` @ a29ff8e). All inventory below was verified against source on that date.

## Phase 0 — Preconditions (get sign-off first)

- **Repo transfer.** The project is planned to move to `spawnia/lighthouse` (README notice, discussion #2767). Whether v7 ships before or after the transfer is a **maintainer decision** — do not assume. Confirm before starting.
- **This skill may not route around change control.** Every item lands via `lighthouse-change-control`'s gates.
- **v7 ends v6 feature work** (README versioning promise: only the current major gets features/fixes). Confirm the maintainer accepts closing v6 to features on release.

If transfer status is unresolved → **stop and ask the maintainer**; do not start renaming/removing.

## Phase 1 — Inventory (run these; compare to the expected findings)

```bash
# Already-committed v7 breaking changes:
awk '/## v6 to v7/,/## v5 to v6/' UPGRADE.md | grep "^###"
# Deprecation removal candidates:
grep -rn "@deprecated" src --include="*.php"
# graphql-php version guards that a constraint bump would clear:
grep -n "graphql-php\|webonyx" src/GraphQL.php
# Current graphql-php constraint:
grep "webonyx/graphql-php" composer.json
```

**Expected findings on 2026-07-07 (if these changed, update the cut-list first):**

Committed v7 breaks in `UPGRADE.md` — all four have their v6 groundwork already in `src`:
1. *Leverage automatic test trait setup* — `RefreshesSchemaCache::bootRefreshesSchemaCache()` is `@deprecated`; auto-setup replaces it.
2. *`EnsureXHR` enabled in default config* — middleware exists (`src/Http/Middleware/EnsureXHR.php`), currently **commented out** in `src/lighthouse.php:32`. v7 uncomments it.
3. *`@can` → `@can*` split* — the specialized directives already exist (`src/Auth/CanFindDirective.php`, `CanModelDirective`, `CanQueryDirective`, `CanResolvedDirective`, `CanRootDirective`); `src/Auth/CanDirective.php` is `@deprecated TODO remove with v7`.
4. *`lighthouse:clear-cache` → `lighthouse:clear-schema-cache`* — `src/Console/ClearCacheCommand.php` is `@deprecated in favor of lighthouse:clear-schema-cache`.

Deprecation inventory (removal candidates): `src/Auth/CanDirective.php`, `src/Console/ClearCacheCommand.php`, `src/Testing/RefreshesSchemaCache.php` (the boot method), `src/Testing/MakesGraphQLRequests.php` (`@deprecated use TestsSubscriptions`), `src/Testing/MakesGraphQLRequestsLumen.php` (Lumen support "removed in the next major").

Config default change: `tracing.driver` v7 default → `FederatedTracing` (comment in `src/lighthouse.php`).

graphql-php constraint: currently `^15`. Version guards to clear on a bump: `src/GraphQL.php:166` (`method_exists(... 'getQueryComplexity')`) and the `@phpstan-ignore` TODOs at lines ~399/405/424. A bump would also enable the clean #2771 fix (`SchemaConfig::setScalarOverrides()`).

If the deprecation grep returns hits not listed above → add them to the cut-list before proceeding.

## Phase 2 — The cut-list decision menu

Ranked: committed items first, then proposals. Each needs its proof obligation met before merge.

| # | Candidate | Motivation | Proof obligation | Blast radius | Decision |
|---|---|---|---|---|---|
| 1 | Enable `EnsureXHR` by default | CSRF hardening | Full matrix green; UPGRADE entry (exists); doc note that GET/HTML-form POST break | Medium (rejects some request shapes) | Committed |
| 2 | Remove `@can`, keep `@can*` | Clearer authorization directives | Grep no `CanDirective` refs in src/docs; UPGRADE diff (exists) | Medium (schema authors migrate) | Committed |
| 3 | Auto test-trait setup | Simpler test API | Test suite green without the boot calls; UPGRADE entry (exists) | Low (test code only) | Committed |
| 4 | Rename clear-cache command | Consistency | Command removed; help text updated | Low | Committed |
| 5 | `tracing.driver` → FederatedTracing default | Federation-first | Tracing tests green under new default; UPGRADE entry | Low–Medium (tracing consumers) | Committed (config comment) |
| 6 | Drop Lumen support | Lumen support is `@deprecated` | Remove `MakesGraphQLRequestsLumen`; matrix drops Lumen cells | Medium (Lumen users) | **OPEN — maintainer call** |
| 7 | **#2744 scope nested mutations to parent** | Security: prevents cross-parent writes | Repro test from #2744 fails-then-passes; ALL existing nested-mutation tests still green; UPGRADE entry; decide default-on vs opt-in | High (changes mutation behavior) | **OPEN — maintainer call** |
| 8 | Bump `webonyx/graphql-php` floor past `^15` | Clears version guards; enables clean #2771 fix | Verify target release ships `setScalarOverrides`; matrix green on new floor | High (users on older 15.x) | **OPEN — depends on upstream** |
| 9 | Drop EOL PHP/Laravel from matrix | Simplifies support | State exactly what's dropped; matrix updated | Medium | **OPEN — maintainer call** |

## Phase 3 — Execution order and per-change gates

For each item, in this order (low-risk first):

1. Write the change on a branch (never `master` directly).
2. If behavioral: **failing test / characterization test first** (see `lighthouse-testing-and-qa`).
3. Land the `UPGRADE.md` entry **in the same PR** (PR template requires documenting breaking changes).
4. `make it` green locally, then full CI matrix green.
5. For #2744 specifically: implement behind the maintainer's chosen mechanism (default-on / opt-in flag / new directive); the #2744 exploitation example must fail with an authorization/scope error while **every** existing nested-mutation test still passes (they live in `tests/Integration/Execution/MutationExecutor/` — `HasManyTest`, `BelongsToTest`, `MorphToTest`, etc. — and `tests/Integration/Schema/Directives/` — `CreateDirectiveTest`, `UpsertDirectiveTest`, …). Prove via `lighthouse-proof-and-analysis-toolkit`.
6. For the graphql-php bump (#8): first confirm the target upstream release exists and ships `setScalarOverrides`; only then bump the constraint and replace the `method_exists` guards.

## Phase 4 — Release gates (all must pass, measurably)

- [ ] CI matrix **fully green** on the release SHA (all PHP×Laravel×lowest/highest cells).
- [ ] `php artisan lighthouse:print-schema --sort` diff of v6→v7 reviewed; **every** difference traced to an `UPGRADE.md` entry (use `scripts/schema-diff.sh` from `lighthouse-diagnostics-and-tooling`).
- [ ] `CHANGELOG.md` has a complete `## v7.0.0` section; every entry ends in a PR URL.
- [ ] Docs copied to `docs/7` **and** the `Makefile` `release` target updated from `docs/6` to `docs/7`.
- [ ] Benchmarks within noise (rstdev) of the v6 baseline, or each regression explained (`bench-report.sh`).
- [ ] All `@deprecated`-for-v7 elements removed; `grep -rn "@deprecated.*v7\|remove with v7" src` returns nothing stale.
- [ ] The #2771 fix landed (if graphql-php bumped) with a regression test asserting `possibleTypes()` is not invoked on a simple execution.

## Wrong paths (fenced off — with why)

- **Reusing the `@can` name for changed semantics.** `@can` is being removed, not repurposed. A directive that changed behavior under the same name silently breaks every schema. Keep the `@can*` split.
- **Shipping #2744 scoping default-on in a v6 minor.** It changes mutation behavior → breaking → v6-minor is forbidden. v7 or opt-in only.
- **Bumping the graphql-php floor in a v6 minor.** Current constraint is `^15`; raising the floor breaks users on older 15.x → breaking. Guard with `method_exists` in v6 (precedent: #2637); bump only in v7.
- **Deleting `docs/6`.** Archived-major docs stay; `make release` copies master into the *new* dir, it doesn't remove old ones (beyond the one it's writing).
- **Skipping UPGRADE entries for "small" breaks.** Every break, however small, gets an entry — that's how users survive the upgrade.

## Branches: "if you see X instead → Y"

- Phase 1 grep shows a new `@deprecated` not in the inventory → add it to the cut-list (Phase 2) before executing.
- The schema diff (Phase 4) shows a difference with no UPGRADE entry → **stop**; either revert that change or add the entry; an undocumented break must not ship.
- A benchmark regresses beyond rstdev with no explanation → **stop**; treat as a regression, bisect (`lighthouse-proof-and-analysis-toolkit`), don't ship.
- Upstream `setScalarOverrides` not released yet → keep #2771 on the `method_exists`-guard path; do not bump the floor.

## When NOT to use this skill

- Day-to-day, non-major changes → `lighthouse-change-control`.
- Proving a single change's correctness/BC → `lighthouse-proof-and-analysis-toolkit`.
- History of why an item is where it is → `lighthouse-failure-archaeology`.
- Longer-horizon "beyond SOTA" work (not the v7 release) → `lighthouse-research-frontier`.

## Provenance and maintenance

**Most volatile skill — re-verify the whole inventory before acting.** Verified 2026-07-07 against v6.68.0 (`master` @ a29ff8e).

```bash
awk '/## v6 to v7/,/## v5 to v6/' UPGRADE.md | grep "^###"   # committed v7 breaks
grep -rn "@deprecated" src --include="*.php"                  # removal candidates
grep -n "EnsureXHR" src/lighthouse.php                        # still commented out?
ls src/Auth/ | grep Can                                       # @can* directives present
grep "webonyx/graphql-php" composer.json                      # constraint (was ^15)
grep -n "tracing" -A15 src/lighthouse.php | grep -i "v7\|FederatedTracing"
grep -n "release:" -A2 Makefile                               # docs/6 hardcode
```
Open decisions left to the maintainer: transfer timing (Phase 0); #2744 mechanism and default (item 7); Lumen drop (item 6); graphql-php floor bump (item 8, also gated on upstream); matrix drops (item 9).
