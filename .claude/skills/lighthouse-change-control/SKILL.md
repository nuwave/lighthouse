---
name: lighthouse-change-control
description: >-
  Gate and classify any change to Lighthouse before committing or opening a PR.
  Use when deciding whether a change is a fix/feature/deprecation/breaking change,
  what target version it belongs in, whether it needs a CHANGELOG or UPGRADE.md entry,
  or whether it breaks the @api contract or a schema consumer. Load before merging,
  releasing, bumping a dependency constraint, changing a config default, or altering
  schema output, response shape, or error messages. Encodes the project's
  non-negotiables (SemVer, @api stability, no breaking changes for schema consumers)
  with their rationale and the incidents behind them.
---

# Lighthouse change control

This is the gate every change passes through.
Read it before you commit, before you open a PR, and before you cut a release.
Lighthouse is a widely-deployed library: a bad release breaks thousands of production GraphQL APIs at once, and there is no staged rollout.
The bar is correctness and backward compatibility, not speed.

Ground truth date: 2026-07-07, verified against v6.68.0 (`master` @ a29ff8e).

## First: classify the change

| Class | CHANGELOG entry? | Tests required? | Docs (`/docs`) | UPGRADE.md | Target version |
|---|---|---|---|---|---|
| Docs-only | No (policy) | No | The change itself | No | any |
| Bug fix | Yes — `Fixed` | Yes — failing test first | If behavior was documented | No | current major, next patch |
| New feature | Yes — `Added` | Yes | Yes | No | current major, next minor |
| Deprecation | Yes — `Deprecated` | Keep tests green | Note the replacement | No (until removal) | current major, next minor |
| Breaking change | Yes — `Changed`/`Removed` | Yes | Yes | **Yes, required** | **next major only** |

The "docs-only changes need no CHANGELOG entry" rule is explicit project policy — see commit `b095405` ("Clarify @search empty/null semantics and docs-only changelog policy") and CONTRIBUTING.md → Changelog.

CHANGELOG entries go at the top of the `## Unreleased` section in `CHANGELOG.md`, in the correct category, and **end with the full PR URL**: `https://github.com/nuwave/lighthouse/pull/<number>`.
Categories are fixed (Keep a Changelog): `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`.

## The non-negotiables (with rationale and the incident behind each)

### 1. Semantic Versioning; only the current major gets features and fixes

README → Versioning: "Only the current major version receives new features and bugfixes."
Minor upgrades must require **no** changes to a user's PHP code or GraphQL schema, and cause **no** breaking behavioral change for API consumers.
Rationale: users pin `^6` and expect `composer update` within a major to be safe forever.

### 2. The `@api` PHPDoc contract is the code-stability boundary

Code elements marked with the `@api` PHPDoc tag are guaranteed stable until the next major (CONTRIBUTING.md → Internal).
Everything **not** marked `@api` is internal and may change in any release.
There are currently 33 `@api` annotations across `src` (`grep -rc "@api" src` to recount).
Load-bearing examples to respect: `src/Support/Contracts/GraphQLContext.php`, `src/Support/Contracts/ArgResolver.php`, `src/Support/Contracts/SaveAwareArgResolver.php`, `src/Execution/ErrorHandler.php`, `src/Schema/TypeRegistry.php`, `src/Schema/Directives/BaseDirective.php`.
Before changing any signature, run `grep -n "@api" <file>` — if the method or class carries it, the change is breaking and goes to the next major.

### 3. No breaking changes for schema consumers within a major (the unwritten rule, now written)

This is stronger than the `@api` code contract and is the maintainer's hardest line.
A client of a running Lighthouse GraphQL API must never observe breakage from a minor/patch upgrade. Frozen surfaces:

- **Schema output** — the printed SDL (types, fields, nullability, directive locations, enum values).
- **Response shapes** — the JSON structure of query/mutation/subscription results, including pagination wrappers.
- **Error semantics** — error `message`, `extensions`, categories, and where errors surface.

Incidents that set this rule:

- **`88d8e18f` "Revert breaking schema change in generate pagination types (#2104)"** — a change altered the generated pagination types in the printed schema; it shipped, broke consumers' schemas, and was reverted with entries added to both CHANGELOG.md and UPGRADE.md. Touches `src/Pagination/PaginationManipulator.php`. Lesson: any diff to generated schema types is breaking even if the PHP API is untouched.
- **`150b5401` "Prevent regression to simple paginator type on fields using `@cache` (#2355)"** — `@cache` combined with pagination silently downgraded the paginator type in the schema; caught and fenced with a regression test. Lesson: feature interactions can break schema output; test the combinations.

How to check before you ship: see "Is my change breaking?" below.

### 4. Full support-matrix compatibility

CI (`.github/workflows/validate.yml`) runs PHPStan and PHPUnit across **PHP 8.0–8.5 × Laravel ^9–^13 × lowest/highest dependencies** (with documented exclusions).
"Works on my PHP 8.4 / Laravel 12" is not evidence.
A change is not done until the whole matrix is green.
Do not drop a supported PHP/Laravel version in a minor — that is breaking (next major only).

### 5. Extensibility guarantees

From CONTRIBUTING.md → Code Guidelines:

- Use `protected` over `private` everywhere in `src/` — subclasses must be able to override.
- Never use `final` in `src/`; always use `final` in `tests/`.
- Full namespace in PHPDoc (`@var \Full\Namespace\Class`), imports in code.
- No Laravel Facades (Lumen compatibility) — inject dependencies. The one sanctioned exception is the `response()` helper.

Violating these does not break users today but removes an escape hatch they rely on, so reviewers treat it as blocking.

## Review and merge gates

Run before every commit:

```bash
make it        # = vendor + fix + stan + test (the full local gate)
```

Individually:

```bash
make fix       # rector + php-cs-fixer + prettier (auto-format)
make stan      # PHPStan level 8 (phpstan.neon)
make test      # PHPUnit
```

- PHPStan runs at **level 8** (`phpstan.neon`; `# TODO level up to max`). Do not lower it. Adding an ignore is acceptable only when it mirrors an existing documented pattern in `phpstan.neon` (e.g. cross-Laravel-version generics noise).
- A bug fix without a failing-test-first is not acceptable (CONTRIBUTING.md → Testing). Write the test that reproduces the bug, watch it fail, then fix.
- **The format workflow auto-commits to your branch.** `.github/workflows/format.yml` runs `composer normalize` and Prettier on every push and pushes the result back with `git-auto-commit-action`. After pushing, `git pull` before adding more commits or you will hit a non-fast-forward.

## Release protocol

From CONTRIBUTING.md → "Release a New Version":

1. Review the entries under `CHANGELOG.md` → `## Unreleased`; add any missing ones.
2. Based on those entries and the previous version, decide the next version number (SemVer) and add it to `CHANGELOG.md` (replace `## Unreleased` header with the version, add a fresh empty `## Unreleased` above).
3. Draft a new GitHub release.
4. Use the version number as both git tag and title.
5. Paste the changelog entries as the description.
6. Publish.

Breaking changes are documented in `UPGRADE.md` and may only ship in a **major** release.
A major release also ends feature work on the previous major (README versioning promise) and requires copying `docs/master` to a new numbered docs dir — see `lighthouse-v7-campaign` for the full major-release runbook.

Deprecation path: mark the element with a `@deprecated` PHPDoc tag stating the replacement, add a `Deprecated` CHANGELOG entry, keep it working until the next major, then remove it.
Real current examples (`grep -rn "@deprecated" src`):
`src/Console/ClearCacheCommand.php` (`@deprecated in favor of lighthouse:clear-schema-cache`), `src/Auth/CanDirective.php` (`@deprecated TODO remove with v7`), `src/Testing/RefreshesSchemaCache.php`, `src/Testing/MakesGraphQLRequestsLumen.php`.

## Decision aid: "Is my change breaking?"

Answer NO to all of these or it is breaking (→ next major, → UPGRADE.md entry):

- [ ] Printed schema is byte-identical for an unchanged input schema. Verify: `php artisan lighthouse:print-schema` before and after, `diff` the two. Empty diff required for non-breaking work.
- [ ] No `@api`-annotated signature changed. Verify: `grep -n "@api" <touched files>`.
- [ ] Response JSON shape unchanged for existing queries/mutations/subscriptions.
- [ ] No config default in `src/lighthouse.php` changed in a way that alters behavior for an existing app.
- [ ] No error `message`/`extensions` that a test (or a user) relies on changed.
- [ ] No supported PHP/Laravel version dropped; no dependency floor raised.
- [ ] New required arguments/fields were not added to existing directives or generated types.

If any box cannot be checked, the change is breaking. Do not "sneak" it into a minor.

## When NOT to use this skill

- Actually diagnosing a bug → `lighthouse-debugging-playbook`.
- Turning a report into a failing test → `lighthouse-issue-triage-and-reproduction`.
- Executing a major release end-to-end → `lighthouse-v7-campaign`.
- Proving a change is non-breaking with measurements → `lighthouse-proof-and-analysis-toolkit`.
- The history behind a settled decision → `lighthouse-failure-archaeology`.

## Provenance and maintenance

Verified 2026-07-07 against v6.68.0 (`master` @ a29ff8e). Re-verify drift with:

```bash
grep -n "Release a New Version" CONTRIBUTING.md          # release steps still present
grep -rc "@api" src                                       # @api surface count (was 33)
grep -rn "@deprecated" src --include="*.php"              # deprecation inventory
git show 88d8e18f --stat                                  # pagination-schema revert incident
git log --oneline -3 -- .github/workflows/format.yml      # auto-format workflow still active
grep -n "level:" phpstan.neon                             # PHPStan level (was 8)
```
