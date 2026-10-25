---
name: lighthouse-diagnostics-and-tooling
description: >-
  Measure Lighthouse behavior instead of eyeballing it. Load when you need to inspect
  or compare the generated schema, validate it, count queries, benchmark performance,
  trace a request, read PHPStan output, or profile a regression. Catalogs every
  lighthouse:* artisan command with its exact signature, gives measurement recipes
  with interpretation guides, and ships runnable scripts (schema-diff, config-keys,
  bench-report). For what the results should be (acceptance bar) use
  lighthouse-testing-and-qa; for proof strategy use lighthouse-proof-and-analysis-toolkit.
---

# Lighthouse diagnostics and tooling

Rule of the house: **measure, don't guess.** This skill lists the instruments and how to read them.

Ground truth date: 2026-07-07, v6.68.0 (`master` @ a29ff8e). Command signatures were read from `src/Console/*.php`. Commands shown are the native `php artisan …` form; under Docker prefix with `docker compose run --rm php vendor/bin/artisan …`.

## Artisan command catalog

| Command | What it does | Reach for it when |
|---|---|---|
| `lighthouse:print-schema` | Compile and print the schema. Options: `--write`/`-W`, `--disk=`/`-D`, `--json`, `--federation`, `--sort`. | Comparing schema before/after a change (use `--sort` for stable diffs). |
| `lighthouse:validate-schema` | "Validate the GraphQL schema definition." | Confirming SDL + directives build cleanly. |
| `lighthouse:ide-helper` | "Create IDE helper files to improve type checking and autocompletion." | Regenerating directive/type helpers. |
| `lighthouse:cache` | "Compile the GraphQL schema and cache it." | Warming the schema cache in deploys. |
| `lighthouse:clear-schema-cache` | "Clear the GraphQL schema cache." | Schema edits not taking effect. |
| `lighthouse:clear-query-cache` | "Clears the GraphQL query cache." | Stale parsed-query cache. |
| `lighthouse:clear-cache` | **`@deprecated`** in favor of `lighthouse:clear-schema-cache`. | Legacy; don't use in new work. |
| `lighthouse:directive/field/query/mutation/subscription/interface/union/scalar/validator` | Generator stubs. | Scaffolding new code. |

## Measurement recipes (with interpretation)

### Schema backward-compatibility check
```bash
php artisan lighthouse:print-schema --sort > before.graphql
# make your change
php artisan lighthouse:clear-schema-cache
php artisan lighthouse:print-schema --sort > after.graphql
diff -u before.graphql after.graphql
```
Interpretation: **empty diff = non-breaking for consumers** (the hard rule). Any removed field/type, changed nullability, or changed default is breaking. Use `scripts/schema-diff.sh` to do this across two git refs automatically.

### Schema validity
```bash
php artisan lighthouse:validate-schema
```
Non-zero exit / thrown `DefinitionException` = your SDL or a directive's AST manipulation is invalid.

### Query counting (N+1)
Use a `DBTestCase` with `AssertsQueryCounts`:
```php
$this->assertQueryCountMatches($expected, function (): void {
    $this->graphQL(/* ... */);
});
```
Interpretation: build **more than one** parent row, or batching and N+1 look identical. Real usage: `tests/Integration/Execution/DataLoader/RelationBatchLoaderTest.php`. See `lighthouse-testing-and-qa`.

### Benchmarks
```bash
make bench      # docker: phpbench run --report=aggregate
# native: vendor/bin/phpbench run --report=aggregate
```
Config: `phpbench.json` (bootstrap `vendor/autoload.php`, path `benchmarks`). Benchmark classes (`@Warmup(1) @Revs(10) @Iterations(10)`):
- `QueryBench` — base for query execution benchmarks.
- `HugeResponseBench` — large response assembly cost.
- `HugeRequestBench` — large request parsing cost.
- `ASTUnserializationBench` — schema-cache AST unserialize cost (relevant to #2771/schema cache work).

Interpretation discipline: run on the **same machine**, look at the **`rstdev`** (relative standard deviation) column before trusting any delta — a change smaller than rstdev is noise. Baseline first, change, re-run; explain every regression. Use `scripts/bench-report.sh before` / `... after` to capture dated reports.

### Request tracing
Set `tracing.driver` to `ApolloTracing` (JSON timings per field) or `FederatedTracing` (protobuf; v7 default). Enables per-field resolution timing in the response `extensions`.

### Query logging
Uncomment `Nuwave\Lighthouse\Http\Middleware\LogGraphQLQueries` in `lighthouse.route.middleware` to log every incoming query.

### Error verbosity
`LIGHTHOUSE_DEBUG` bitmask 0–15 (table in `src/lighthouse.php`), effective only with Laravel `app.debug=true`. Raise it to surface messages/traces; lower it to reproduce production masking.

### Profiling a performance regression (the #2771 method)
1. Capture a cachegrind profile (Xdebug) of a minimal operation (e.g. authenticated `{ __typename }`) on the suspect version and the prior version.
2. Compare total PHP work (cachegrind file size / instruction counts) — #2771 saw 9.1 MB → 57 MB.
3. **Ablation:** neutralize the suspected cause (e.g. patch `setTypes(fn () => [])`) and re-measure; if the baseline returns, the cause is confirmed. This ablation + before/after comparison is the gold-standard method — see `lighthouse-proof-and-analysis-toolkit`.

## Static analysis
```bash
make stan       # docker: vendor/bin/phpstan --verbose
```
`phpstan.neon`: **level 8** (`# TODO level up to max`), bootstraps `phpstan-bootstrap.php`, stubs `_ide_helper.php`, scans `benchmarks/src/tests`. `reportUnmatchedIgnoredErrors: false` because multi-Laravel-version support creates version-specific dead spots. Adding an `ignoreErrors` entry is acceptable only when it mirrors an existing documented pattern (e.g. Laravel generics arity noise). Never lower the level.

## Shipped scripts (`scripts/`)

| Script | Purpose | Verified |
|---|---|---|
| `schema-diff.sh <ref-before> <ref-after>` | Diff printed schema across two git refs; non-empty diff fails. | `bash -n` OK 2026-07-07 (not executed — needs vendor/). |
| `config-keys.php [path]` | Tokenizer-based list of config keys in `src/lighthouse.php` (no Laravel boot). | Executed with PHP 8.4 on 2026-07-07 — prints the keys. |
| `bench-report.sh [label]` | Run PHPBench, tee to a dated `bench-reports/` file. | `bash -n` OK 2026-07-07 (not executed — needs vendor/). |

## When NOT to use this skill

- The acceptance bar / what evidence is required → `lighthouse-testing-and-qa`.
- Proof strategy (how to combine tools into airtight evidence) → `lighthouse-proof-and-analysis-toolkit`.
- Triaging a live failure fast → `lighthouse-debugging-playbook`.
- Config values behind these tools → `lighthouse-config-and-flags`.

## Provenance and maintenance

Verified 2026-07-07 against v6.68.0 (`master` @ a29ff8e). Re-verify with:

```bash
grep -rn "lighthouse:" src/Console/*.php | grep -oE "lighthouse:[a-z-]+" | sort -u   # command names
sed -n '/protected \$signature/,/SIGNATURE;/p' src/Console/PrintSchemaCommand.php     # print-schema options
cat phpbench.json ; ls benchmarks/
grep -n "level:" phpstan.neon
php .claude/skills/lighthouse-diagnostics-and-tooling/scripts/config-keys.php | wc -l  # re-run the shipped script
```
