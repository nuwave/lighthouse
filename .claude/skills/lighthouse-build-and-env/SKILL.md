---
name: lighthouse-build-and-env
description: >-
  Recreate and operate the Lighthouse development environment. Load when setting up
  the project, running make targets, choosing Docker+Make vs native tooling,
  understanding the docker-compose services (php/mysql/redis/node), configuring the
  test database, regenerating protobuf or agent config, or diagnosing environment
  traps (Xdebug slowness, auto-format commits, gitignored phpunit.xml, packagist
  egress blocks, CI removing dev deps). For what the test suites assert and how to
  add tests use lighthouse-testing-and-qa; for measurement tools use
  lighthouse-diagnostics-and-tooling.
---

# Lighthouse build and environment

Two supported paths: **Docker + Make** (reproducible, recommended) and **native tools**. This skill lets you stand up either from scratch.

Ground truth date: 2026-07-07, v6.68.0 (`master` @ a29ff8e). Verified by reading `Makefile`, `docker-compose.yml`, `php.dockerfile`, `node.dockerfile`, `composer.json`, `phpunit.xml.dist`, `.github/workflows/validate.yml`, and `.ai/`. Commands here were **config-verified, not executed** in the authoring environment (which has no `vendor/`).

## Docker + Make happy path

```bash
make setup     # build containers + install deps + docs deps + generate agent config
make it        # the pre-commit gate: vendor + fix + stan + test
```

### Every Make target (from `Makefile`)

| Target | Does |
|---|---|
| `make help` | List targets with descriptions. |
| `make setup` | `build` + `vendor` + `docs/node_modules` + `ai-sync`. |
| `make build` | Build Docker images, passing `USER_ID`/`GROUP_ID` (file-ownership; short `id` opts for macOS, PR #2504). |
| `make it` | `vendor fix stan test` — run before commits. |
| `make fix` | `rector` + `php-cs-fixer` + `prettier`. |
| `make rector` | Refactor via Rector. |
| `make php-cs-fixer` | Format PHP. |
| `make prettier` | Format docs markdown/js (via `node-docs`). |
| `make stan` | PHPStan (level 8). |
| `make test` | PHPUnit. |
| `make bench` | PHPBench aggregate report. |
| `make vendor` | `composer update` + `composer validate --strict` + `composer normalize`. |
| `make php` | Interactive shell in php container. (Denied in `.ai/settings.json` for agents.) |
| `make node` | Interactive shell in node container. (Denied for agents.) |
| `make release` | `rm -rf docs/6 && cp -r docs/master docs/6` — **hardcodes `docs/6`**; a new major must edit this. |
| `make docs` | Dev server for docs (port 8081). |
| `make docs/node_modules` | Install docs yarn deps. |
| `make ai-sync` | Regenerate agent config from `.ai/` via `lnai@0.6.7`. |
| `make proto` | Regenerate protobuf PHP classes with `buf`. |
| `make proto/update-reports` | Refresh `reports.proto` from Apollo. |

### Single test

```bash
docker compose run --rm php vendor/bin/phpunit --filter=TestClassName
docker compose run --rm php vendor/bin/phpunit --filter=testMethodName
docker compose run --rm php vendor/bin/phpunit tests/Unit/Path/To/TestFile.php
```
(From `.ai/AGENTS.md`.)

## Services (`docker-compose.yml`)

| Service | Image / build | Notes |
|---|---|---|
| `php` | `php.dockerfile` (`php:8.3-cli` + zip, mysqli, pdo_mysql, intl, bcmath, xdebug, redis; `memory_limit=-1`) | Build args `USER_ID`/`GROUP_ID` for file ownership. |
| `mysql` | `mysql:5.7`, `tmpfs:/var/lib/mysql`, `platform: linux/amd64` | Pinned 5.7 (`# TODO switch to MySQL 8`, issue #1784). `platform` pin is for Apple Silicon. Empty root password. |
| `redis` | `redis:6` | Subscription storage / cache tests. |
| `node-docs` | `node.dockerfile` (`node:22-slim`) | Docs dev server, port 8081. |
| `node-tools` | `node.dockerfile` | Used by `make ai-sync` (lnai). |

## Native path

Requirements: PHP `^8` with extensions `mbstring, mysqli, pdo_mysql, redis` (from `validate.yml` `REQUIRED_PHP_EXTENSIONS`; `intl`/`bcmath`/`zip` also used per the Dockerfile), Composer 2, MySQL (any Laravel-supported version), Redis 6.

```bash
composer install
cp phpunit.xml.dist phpunit.xml       # then edit the <env> DB/Redis params to reach your instances
vendor/bin/phpunit                     # run tests
vendor/bin/phpstan --verbose           # static analysis
vendor/bin/php-cs-fixer fix            # format
```
`composer install` runs `post-autoload-dump` → `testbench package:discover` (`composer.json` scripts).
`phpunit.xml` is **gitignored** (per-developer) — you must copy it.

## Known traps (each verified)

- **Xdebug slows tests ~10×.** The php image installs Xdebug. CONTRIBUTING.md: "Enabling Xdebug slows down tests by an order of magnitude. Stop listening for Debug Connection to speed it back up." Set `XDEBUG_REMOTE_HOST` to your host IP as seen from the container.
- **The format workflow auto-commits to your branch.** `.github/workflows/format.yml` runs `composer normalize` + Prettier on every push and pushes the result back (`git-auto-commit-action`, `creyD/prettier_action`). After pushing, `git pull` before continuing or you'll hit a non-fast-forward.
- **`phpunit.xml` is gitignored.** Copy from `phpunit.xml.dist`; your edits stay local.
- **`vendor/` is absent until `composer install`.** You cannot run phpunit/phpstan/phpbench without it. A cloud/CI sandbox with restricted egress may block packagist entirely (symptom: `composer` fails with a proxy `CONNECT … 403`). If so, dependency-requiring commands can't run there — verify by reading config instead.
- **CI removes some dev deps.** `validate.yml` runs `composer remove --dev --no-update phpbench/phpbench rector/rector` (and `larastan`, `phpstan-mockery` in the test job) because they conflict on the lowest/oldest matrix cells, and removes `laravel/pennant` on Laravel 9 (incompatible). So CI runs a slightly different dependency set than your local highest-deps install — see `lighthouse-testing-and-qa` for why "green locally" ≠ done.
- **MySQL 5.7 pin.** Tests target MySQL 5.7; behavior may differ on MySQL 8 until #1784 is resolved.
- **Proto regeneration needs `buf`.** `make proto` runs `bufbuild/buf` via Docker and `sudo chown`s the output; don't hand-edit `src/Tracing/FederatedTracing/Proto/`.

## Agent configuration (how `.claude` relates to `.ai`)

- `.ai/AGENTS.md` is the **source** of agent guidance; `.ai/config.json` and `.ai/settings.json` configure which tools get generated files and their permissions.
- `make ai-sync` runs `lnai` to generate tool-specific files. The generated `.claude/CLAUDE.md` and `.claude/settings.json` are **gitignored** (`.gitignore` → `# lnai-generated`).
- **`.claude/skills/` is version-controlled** — that is where this skill library lives. It is not generated by lnai and is not gitignored.

## When NOT to use this skill

- What the tests assert / how to add one → `lighthouse-testing-and-qa`.
- Diagnostic/measurement tooling (benchmarks, query counts, tracing) → `lighthouse-diagnostics-and-tooling`.
- Config option meanings → `lighthouse-config-and-flags`.
- Running/operating a Lighthouse app as a user would → not covered here; this is the *dev* environment for the library itself.

## Provenance and maintenance

Verified 2026-07-07 against v6.68.0 (`master` @ a29ff8e); commands config-verified, not executed. Re-verify with:

```bash
grep -E "^\.PHONY|##" Makefile | head -40         # target list + descriptions
grep -n "image:\|platform:\|tmpfs" docker-compose.yml
grep -n "REQUIRED_PHP_EXTENSIONS\|composer remove" .github/workflows/validate.yml
grep -n "lnai\|ai-sync" Makefile
sed -n '/lnai-generated/,/end lnai-generated/p' .gitignore
```
