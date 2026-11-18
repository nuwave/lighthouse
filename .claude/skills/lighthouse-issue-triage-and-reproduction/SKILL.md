---
name: lighthouse-issue-triage-and-reproduction
description: >-
  Turn a Lighthouse bug report or issue into a triaged, reproduced, failing test —
  the core maintenance loop. Load when handling an incoming issue or bug report:
  classifying it (bug/enhancement/question/security/needs-reproduction), checking for
  duplicates, deciding severity, and writing a minimal failing test from the report.
  Provides the triage decision tree, copy-paste test skeletons anchored to real base
  classes, and the security-report route. For fast live-failure triage use
  lighthouse-debugging-playbook; for the fix-PR gating rules use lighthouse-change-control.
---

# Lighthouse issue triage and reproduction

The maintenance workflow: report → classify → check for duplicates → reproduce as a failing test → hand to a fix. A reproduction is the deliverable; opinions without a red test don't move an issue.

Ground truth date: 2026-07-07, v6.68.0 (`master` @ a29ff8e).

## Triage decision tree

1. **Security vulnerability?** → Do **not** discuss in public. Route to GitHub private vulnerability reporting: `https://github.com/nuwave/lighthouse/security/advisories/new` (per `SECURITY.md`). Never open or comment on a public issue for it.
2. **Bug** (Lighthouse behaves incorrectly)? → Reproduce (below). Label `bug`.
3. **Enhancement / feature request?** → Label `enhancement`; no reproduction needed, but a concrete use case is. Route through the feature-proposal template.
4. **Question / usage help?** → Label `question`/`discussion`; point to docs / GitHub discussions (the bug template itself says questions belong on Stack Overflow / Slack / discussions).
5. **Can't reproduce from the report?** → Label `needs reproduction`; ask for a minimal repro (schema + query + versions).

Issue templates live in `.github/ISSUE_TEMPLATE/` (`bug_report.md`, `feature_proposal.md`, `config.yml`). The bug template asks for: description, expected behavior, steps to reproduce, output/logs, Lighthouse version, Laravel version.

Observed labels in use: `bug`, `enhancement`, `discussion`, `question`, `needs reproduction`, `docs`.

**Duplicate check (do this first):**
```bash
grep -n "<keyword>" CHANGELOG.md          # already fixed in a release?
git log --oneline -i --grep="<keyword>"   # already attempted/reverted?
```
Plus search open issues on GitHub. Currently-known OPEN majors (don't re-file): #2771 (lazy schema eager), #2758 (@canResolved + batchload), #2744 (unscoped nested mutations), #2679 (Laravel 12 auto eager loading), #2436 (Octane memory). See `lighthouse-failure-archaeology` for settled ones.

## Severity grading

| Severity | Signals | Examples |
|---|---|---|
| Critical | Security, data integrity, silent data loss | #2744 (nested mutations touch wrong records) → but note it's already documented/known |
| High | Silent performance regression, broad breakage | #2771 (per-request eager schema) |
| Normal | Feature bug with a workaround | most `bug`-labeled issues |
| Low | Cosmetic, docs, edge case | typos, unclear docs |

Security and data-integrity issues jump the queue. Do not promise release timing.

## Reproduction runbook

1. Extract from the report: the **schema**, the **query + variables**, expected vs actual, and versions.
2. Pick the base class and suite (see `lighthouse-testing-and-qa`).
3. Write the smallest test that fails for the reported reason. Watch it fail.
4. That failing test is the reproduction; attach it to the issue or the fix PR.

### Skeleton A — schema/resolver bug (no DB)
```php
<?php declare(strict_types=1);

namespace Tests\Unit\<Area>;

use Tests\TestCase;

final class SomeBugTest extends TestCase
{
    public function testDescribesTheBug(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: String @field(resolver: "Tests\\Utils\\Queries\\Foo")
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        { foo }
        GRAPHQL)->assertJson([
            'data' => ['foo' => 'expected'],
        ]);
    }
}
```

### Skeleton B — Eloquent / DB bug
```php
<?php declare(strict_types=1);

namespace Tests\Integration\<Area>;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class SomeDbBugTest extends DBTestCase
{
    public function testDescribesTheBug(): void
    {
        // Build data via relations/properties, not FK arrays (CONTRIBUTING style).
        $user = new User();
        $user->name = 'Sepp';
        $user->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User { id: ID! name: String! }
        type Query { users: [User!]! @all }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        { users { id name } }
        GRAPHQL)->assertJsonCount(1, 'data.users');
    }
}
```

### Skeleton C — config-dependent bug
```php
protected function getEnvironmentSetUp($app): void
{
    parent::getEnvironmentSetUp($app);
    $app->make(\Illuminate\Contracts\Config\Repository::class)
        ->set('lighthouse.batchload_relations', false);
}
```
(The `getEnvironmentSetUp` + `ConfigRepository::set` pattern is how `tests/DBTestCase.php` and others toggle config.)

**Placement:** mirror the `src/` module — pagination → `tests/*/Pagination/`, auth → `tests/*/Auth/`, batch loading → `tests/Integration/Execution/DataLoader/`. Fixtures under `tests/Utils/`; migrations under `tests/database/migrations`.

## What a good fix PR contains

Failing test (now passing) + the fix + `CHANGELOG.md` entry (`Fixed`, with PR URL) + docs update if user-facing. Gate it through `lighthouse-change-control` (is it breaking? then it's v7-only).

## Fences (don't do these)

- Don't label something a bug if it's **documented behavior** — *but* documented ≠ acceptable. Worked example: #2744 (unscoped nested mutations) is documented under "Security considerations" in `docs/master/eloquent/nested-mutations.md` ("Lighthouse has no mechanism for fine-grained permissions of nested mutation operations … Make sure that fields with nested mutations are only available to users who are allowed to execute all reachable nested mutations."), yet it is still accepted as a real security issue to fix in v7. The nuance: a documented limitation can still be a legitimate issue when it violates user expectations of safety. Note the tension in triage rather than closing as "works as documented".
- Don't promise a version/date.
- Don't discuss security issues in public threads.

## When NOT to use this skill

- Fast triage of a live failure you're actively debugging → `lighthouse-debugging-playbook`.
- Whether/how the fix is breaking and where it targets → `lighthouse-change-control`.
- Test infrastructure details and assertions → `lighthouse-testing-and-qa`.
- History of an area before reproducing → `lighthouse-failure-archaeology`.

## Provenance and maintenance

Verified 2026-07-07 against v6.68.0 (`master` @ a29ff8e). Re-verify with:

```bash
ls .github/ISSUE_TEMPLATE/ ; head -30 .github/ISSUE_TEMPLATE/bug_report.md
cat SECURITY.md
sed -n '44,50p' docs/master/eloquent/nested-mutations.md
grep -n "getEnvironmentSetUp\|ConfigRepository" tests/DBTestCase.php
```
Open-issue list above was current 2026-07-07 — re-check GitHub.
