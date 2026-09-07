---
name: lighthouse-testing-and-qa
description: >-
  What counts as evidence in Lighthouse and how to produce it. Load when writing or
  running tests, choosing between TestCase and DBTestCase, using the GraphQL test
  helpers ($this->graphQL, assertGraphQL* mixins, mockResolver, query-count
  assertions), reproducing a bug as a failing test, or interpreting the CI matrix
  and why "passes locally" isn't done. Covers suite anatomy, testing conventions,
  and the acceptance bar. For measurement tooling (benchmarks, tracing) use
  lighthouse-diagnostics-and-tooling; for proof methods use
  lighthouse-proof-and-analysis-toolkit.
---

# Lighthouse testing and QA

Tests are the currency of this project: a bug fix without a failing-test-first is not accepted, and a feature without tests does not merge.
This skill is how you produce evidence the maintainer will trust.

Ground truth date: 2026-07-07, v6.68.0 (`master` @ a29ff8e). Trait/method names below were read from `src/Testing/` and real tests.

## The evidence bar

| Claim | Required evidence |
|---|---|
| "This bug is fixed" | A test that **failed before** your change and **passes after** (CONTRIBUTING.md → Testing: write the failing test first). |
| "No N+1 / query count is bounded" | A `DBTestCase` using `assertQueryCountMatches(...)` with **>1 parent row** (see below). |
| "Schema unchanged / non-breaking" | `lighthouse:print-schema` diff (see `lighthouse-change-control`) and/or characterization test with `assertExactJson`. |
| "This is faster" | PHPBench numbers, same machine, rstdev checked (`lighthouse-diagnostics-and-tooling`). |

## Suite anatomy

Suites (`phpunit.xml.dist`): **Unit** (`tests/Unit`), **Integration** (`tests/Integration`), **Console** (`tests/Console`).

- **`tests/TestCase.php`** — base for schema/resolver tests. Extends Orchestra Testbench. Registers Lighthouse's service providers (Auth, Async, Bind, Cache, CacheControl, GlobalId, OrderBy, Pagination, SoftDeletes, Testing, Validation, plus Scout/Redis). Uses traits `MakesGraphQLRequests`, `MocksResolvers`, `UsesTestSchema`. Adds a default `PLACEHOLDER_QUERY` (`type Query { foo: Int }`) so a schema is always present.
- **`tests/DBTestCase.php`** — base for anything hitting the database. Extends `TestCase`, adds `AssertsQueryCounts` (from `mattiasgeniar/phpunit-query-count-assertions`). Runs `migrate:fresh` **once** (`static $migrated`), then **TRUNCATEs every table** at the start of each test. Why truncate instead of transactions: transactions "do not reset autoincrement" (code comment), and stable ids matter for assertions. Consequence: DB tests are slower and start from a clean, id-reset slate. Connections: `mysql` (default) and `alternate` (for multi-connection tests).

Pick `TestCase` unless you touch the database; then `DBTestCase`.

## The testing toolkit (verified against `src/Testing/`)

Set a schema for the test:
```php
use Nuwave\Lighthouse\Testing\UsesTestSchema;   // provided via TestCase

$this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
type Query {
    foo: String @field(resolver: "Tests\\Utils\\Queries\\Foo")
}
GRAPHQL;
```

Execute and assert (`MakesGraphQLRequests`; helpers are `protected`):
```php
$this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
{ foo }
GRAPHQL)->assertJson([
    'data' => ['foo' => 'bar'],
]);
```
Other helpers: `postGraphQL(array $data, …)`, `multipartGraphQL(...)` (file uploads), `introspect()`, `introspectType($name)`, `introspectDirective($name)`, `streamGraphQL(...)` (`@defer`), `rethrowGraphQLErrors()`.

Mock resolvers (`MocksResolvers`): `mockResolver($resolverOrValue, $key = 'default')` and `mockResolverExpects($invocationOrder, $key)` — return a PHPUnit InvocationMocker/Stubber so you can assert call counts.

Assertion mixins (`TestResponseMixin`, callable on the `TestResponse`):
`assertGraphQLError`, `assertGraphQLErrorMessage`, `assertGraphQLErrorFree`, `assertGraphQLDebugMessage`, `assertGraphQLValidationError`, `assertGraphQLValidationKeys`, `assertGraphQLValidationPasses`, `assertGraphQLBroadcasted`, `assertGraphQLNotBroadcasted`, `assertGraphQLSubscriptionAuthorized`, `assertGraphQLSubscriptionNotAuthorized`.

Subscriptions: use the `TestsSubscriptions` trait (in v6 you may see `setUpSubscriptionEnvironment()`; that explicit call is removed in v7 — `UPGRADE.md`).

Query counting (real example — `tests/Integration/Execution/DataLoader/RelationBatchLoaderTest.php`):
```php
$this->assertQueryCountMatches($expectedQueryCount, function () use (...): void {
    // run the GraphQL operation
});
```

Schema cache in tests: handled by `RefreshesSchemaCache` (wired through `MakesGraphQLRequests`); its explicit `bootRefreshesSchemaCache()` is `@deprecated` and becomes automatic in v7.

## Conventions (CONTRIBUTING.md + `.ai/AGENTS.md`)

- **Relations over foreign-key arrays.** Build related models via `->associate()`/relation methods, not `['user_id' => …]`.
- **Properties over create-arrays.** `$user = new User(); $user->name = 'Sepp'; $user->save();` not `User::create([...])`.
- **GraphQL literals:** annotate every GraphQL string with `/** @lang GraphQL */`; use nowdoc `<<<'GRAPHQL'` by default, heredoc `<<<GRAPHQL` only when interpolating; preserve intentional whitespace in schema/assertion-sensitive cases.
- **`final` in tests, never in `src/`.**
- **Test placement mirrors `src/` modules.** E.g. pagination internals → `tests/Integration/Pagination/…` and `tests/Unit/Pagination/…`; batch loading → `tests/Integration/Execution/DataLoader/`. Fixtures (Models, Queries, Mutations, Policies, Validators) live in `tests/Utils/`; migrations in `tests/database/migrations`; factories in `tests/database/factories`.

## CI matrix discipline

`.github/workflows/validate.yml` runs PHPStan + PHPUnit across **PHP 8.0–8.5 × Laravel ^9–^13 × lowest/highest deps** (with exclusions), plus a coverage job (PHP 8.5 / Laravel ^13, Codecov) and a phpbench job. MySQL 5.7 + Redis 6 as services.

Why "passes locally" ≠ done:
- CI runs the **lowest** dependency set (`--prefer-lowest`), which your local highest-deps install never exercises.
- CI **removes** some dev deps (`phpbench`, `rector`, `larastan`, `phpstan-mockery`) and removes `laravel/pennant` on Laravel 9.
- The PHP floor is 8.0; a feature using 8.1+ syntax fails there.

When one cell fails: reproduce that exact PHP + Laravel + lowest/highest combination locally before theorizing. Don't "fix" by disabling the cell.

## How to add a test (checklist)

1. Pick the suite/base class: schema/resolver → `TestCase`; DB → `DBTestCase`; artisan command → `tests/Console` (+ `TestCase`).
2. Place it mirroring the `src/` module under test.
3. Add fixtures under `tests/Utils/`; migrations under `tests/database/migrations` if new tables are needed.
4. Set `$this->schema`, run with `$this->graphQL(...)`, assert with the mixins.
5. For a bug fix: confirm it **fails** first.
6. Run it single: `docker compose run --rm php vendor/bin/phpunit --filter=YourTest` (native: `vendor/bin/phpunit --filter=YourTest`).
7. Then `make it` to run the full local gate.

Important for N+1 tests: build **more than one** parent row. With one parent, one-query-per-parent and one-batched-query are indistinguishable — the test can't tell N+1 from correct batching. Real batch-loader tests parametrize `userCount`/`tasksPerUser` for exactly this reason.

## When NOT to use this skill

- Standing up the environment / running tests via Docker → `lighthouse-build-and-env`.
- Benchmarks, tracing, query profiling as tools → `lighthouse-diagnostics-and-tooling`.
- Proof methods (laziness, BC, race conditions) → `lighthouse-proof-and-analysis-toolkit`.
- Turning an issue report into a repro → `lighthouse-issue-triage-and-reproduction`.

## Provenance and maintenance

Verified 2026-07-07 against v6.68.0 (`master` @ a29ff8e). Re-verify with:

```bash
ls src/Testing/                                              # trait inventory
grep -oh "function assertGraphQL[A-Za-z]*" src/Testing/TestResponseMixin.php | sort -u
grep -n "protected function graphQL\|multipartGraphQL\|introspect" src/Testing/MakesGraphQLRequests.php
grep -rn "assertQueryCountMatches" tests | head            # real query-count usage
grep -n "static.*migrated\|truncate" tests/DBTestCase.php
grep -n "php-version\|laravel-version\|prefer-lowest" .github/workflows/validate.yml
```
