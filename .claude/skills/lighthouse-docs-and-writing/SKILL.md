---
name: lighthouse-docs-and-writing
description: >-
  Maintain Lighthouse's documents of record and follow house writing style. Load when
  editing docs/, README, CHANGELOG, UPGRADE, CONTRIBUTING, or SECURITY; registering a
  new docs page; writing a directive doc, changelog entry, or upgrade note; or making
  a public claim (performance, "novel", experimental status) that carries a proof
  obligation. Covers the VuePress docs site mechanics, semantic-line-break style, copy
  templates, and external-positioning discipline. For the gating rules behind a change
  use lighthouse-change-control.
---

# Lighthouse docs and writing

Docs are part of the contract: a user-facing change is not done until the docs of record reflect it.
This skill covers what to update, how the docs site works, house style, templates, and the discipline around public claims.

Ground truth date: 2026-07-07, v6.68.0 (`master` @ a29ff8e).

## Documents of record — which file, when

| File | Answers | Update trigger |
|---|---|---|
| `README.md` | What Lighthouse is; sponsors; versioning promise; repo-transfer notice | Rarely; keep the transfer notice + sponsor links intact |
| `docs/` (VuePress site) | How to use every feature | Any user-facing behavior/feature change |
| `CHANGELOG.md` | What changed per release | Every non-docs-only change |
| `UPGRADE.md` | How to migrate across majors | Every breaking change (majors only) |
| `CONTRIBUTING.md` | How to contribute, style, release steps | Process/style changes |
| `SECURITY.md` | How to report a vulnerability | Rarely |
| `.ai/AGENTS.md` | Guidance for coding agents | When conventions change (then `make ai-sync`) |

## Docs site mechanics (VuePress)

- Layout: `docs/master/` = current version; numbered dirs (`docs/6`, `docs/5`, …) = archived majors; `docs/pages/` = version-independent. Reference: `docs/.github/README.md`.
- **New page:** create the `.md` under `docs/master/<section>/` and register it in `docs/master/sidebar.js`. The sidebar is an array of `{ title, children: [...] }`; children are page paths (string) or `[path, "Custom Label"]` pairs. Example section:
  ```js
  { title: "Eloquent", children: [
      ["eloquent/getting-started", "Getting Started"],
      "eloquent/nested-mutations",
  ]},
  ```
- **Develop:** `make docs` (dev server, port 8081).
- **Cut a major's docs:** `make release` runs `rm -rf docs/6 && cp -r docs/master docs/6` — it **hardcodes `docs/6`**. A new major (v7) requires editing this target to `docs/7` before running (see `lighthouse-v7-campaign`).
- **Prettier runs in CI** over docs (`.github/workflows/format.yml`, `--tab-width=2`) and **auto-commits** the result to your branch. `git pull` after pushing.

## House style

- **Semantic line breaks** (sembr.org): one sentence per line in `*.md` and multiline PHPDoc/comment prose. Do **not** wrap at a column, and do not split a sentence at commas. Adopted project-wide in #2753 (`87a39be`). Do **not** reflow code blocks, snippets, or PHPDoc tags (`@param`/`@return`/`@throws`).
- **Directive docs** follow a fixed shape (`docs/master/api-reference/directives.md`): an `## @directiveName` heading, then the directive's SDL `definition()` verbatim in a ```graphql block (with the doc-comment descriptions), then usage examples. Keep the doc's SDL identical to the class's `definition()`.
- **CHANGELOG entries:** correct Keep-a-Changelog category, one sentence, ending in the full PR URL.
- `@api` PHPDoc marks the stable code surface — don't document internal (non-`@api`) classes as if stable.

## Templates (copy these)

CHANGELOG entry (top of `## Unreleased`, correct category):
```markdown
### Fixed

- Handle explicit `null` for `page` argument in paginated queries https://github.com/nuwave/lighthouse/pull/2735
```

UPGRADE.md breaking-change section:
```markdown
### <Short imperative title of the break>

<One or two sentences, semantic line breaks, on what changed and why.>

```diff
-   old(usage)
+   new(usage)
```
```

Directive doc section (in `api-reference/directives.md`):
```markdown
## @myDirective

```graphql
"""
<Description matching the class definition() doc comment.>
"""
directive @myDirective(
  arg: String!
) on FIELD_DEFINITION
```

<Usage example and notes.>
```

GitHub release notes (CONTRIBUTING → Release a New Version): tag + title = version number; body = the changelog entries for that version.

## External positioning and claims discipline

- **"Faster" needs evidence.** Any performance claim in docs/release notes must rest on PHPBench numbers (`lighthouse-diagnostics-and-tooling`). No unbenchmarked speed claims.
- **Experimental stays labeled experimental.** `@defer` is experimental (config comment); docs must say so. Don't present experimental features as stable.
- **Nothing promises beyond the contract.** Docs must not imply stability for non-`@api` internals or guarantee behavior that SemVer/schema-consumer rules don't (see `lighthouse-change-control`).
- **Repo-transfer notice** (README head, discussion #2767): the project is planned to move to `spawnia/lighthouse`. Keep the notice until the transfer completes; don't remove it as "stale".
- **Sponsor links are load-bearing** (README Sponsors, `.github/FUNDING.yml`) — don't drop them.
- **Upstream-first ethos.** Problems rooted in `webonyx/graphql-php` get an upstream issue/PR plus a local `method_exists` guard, not a local hack that forks behavior (pattern: #2771 proposal following #2637). Document the guard's TODO so it's removed when the constraint is bumped.

## When NOT to use this skill

- Deciding whether a change needs a changelog/upgrade entry at all → `lighthouse-change-control`.
- Producing the benchmark numbers a claim needs → `lighthouse-diagnostics-and-tooling`.
- Positioning research/novelty claims about future work → `lighthouse-research-frontier`.

## Provenance and maintenance

Verified 2026-07-07 against v6.68.0 (`master` @ a29ff8e). Re-verify with:

```bash
sed -n '1,20p' docs/master/sidebar.js                  # sidebar shape
grep -n "release:" -A2 Makefile                         # docs/6 hardcode still there
grep -n "tab-width\|prettier" .github/workflows/format.yml
sed -n '44,50p' docs/master/eloquent/nested-mutations.md   # a real doc section
head -2 CHANGELOG.md ; grep -n "pull/" CHANGELOG.md | head -1   # entry format
cat SECURITY.md
```
