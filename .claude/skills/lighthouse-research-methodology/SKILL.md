---
name: lighthouse-research-methodology
description: >-
  The discipline that turns a hunch into an accepted change in Lighthouse. Load when
  investigating a hard problem, forming a hypothesis, or deciding whether an
  explanation is good enough to act on — especially when tempted to declare victory
  early. Covers the evidence bar (one mechanism explains ALL observations including
  negatives, survives adversarial refutation), predicting numbers before running,
  bisect-vs-reason, upstream-vs-local, and the idea lifecycle from experiment flag to
  adopted change or documented retirement. For concrete proof recipes use
  lighthouse-proof-and-analysis-toolkit; for settled outcomes use lighthouse-failure-archaeology.
---

# Lighthouse research methodology

Smaller/faster models (and tired humans) declare victory early. This skill is the guardrail: what it takes for a hunch to become an accepted change here.

Ground truth date: 2026-07-07, v6.68.0 (`master` @ a29ff8e). History cited was verified via `git show`/`CHANGELOG.md`.

## The evidence bar

A claim is accepted only when:

1. **One mechanism explains ALL observations — including the negatives.** Not just "the failing case fails"; also "the passing case passes for the reason the mechanism predicts," and "the ablation removes the effect." A mechanism that explains the positives but not the negatives is incomplete.
2. **It survives assigned adversarial refutation.** A second session or reviewer actively tries to break it: constructs the counter-case, checks the matrix edges (lowest deps, Lumen, cache on/off, batch loading on/off, PHP 8.0). If nobody tried to break it, it isn't proven.

**Worked positive example (#2771).** Hypothesis: graphql-php ≥ 15.31's scalar-override discovery resolves Lighthouse's `types` callable, building every type per request.
- Positive: every operation, even `{ __typename }`, is slower — explained (any op does a built-in scalar lookup).
- Version boundary: 15.30.2 fine, 15.31 slow — explained (the discovery was introduced there).
- Negative/ablation: `setTypes(fn () => [])` restores the baseline — explained (removes the callable the discovery resolves).
A weaker hypothesis, "graphql-php just got slower," fails the negatives: it can't explain why the boundary is exactly 15.31, nor why an ablation of *one specific callable* fully restores performance. That's how you know the strong hypothesis is the right one.

## Predict numbers before you run

Before running an experiment, **write down the expected observation.** If the result differs, suspect the **hypothesis** first, not the measurement (verify the measurement, then revise the hypothesis). #2771's measured-impact table (predicted multipliers, then measured 1.5–3.4× wall / ~6× work) models this.

Template (fill before running):
```
Hypothesis:   <one sentence, falsifiable>
Mechanism:    <the causal chain, in code terms>
Predictions:  <numbers/observations you expect, incl. what the NEGATIVE/ablation should show>
Experiment:   <exact command or test>
Observed:     <fill after>
Verdict:      <confirmed / refuted / mechanism revised — and why>
```

## Bisect vs reason

- **Reason first** when the mechanism is legible in source (you can read the causal chain). Cheaper and more explanatory.
- **`composer`-constraint bisection** for upstream regressions: pin `webonyx/graphql-php` to successive versions to find the boundary (the #2771 method: 15.30.2 vs 15.31).
- **`git bisect`** for local regressions: `git bisect start; git bisect bad; git bisect good <old-tag>`; run the failing test at each step.
- Combine: bisect to localize, then reason to explain. A boundary without a mechanism is a lead, not a conclusion.

## Upstream vs local

Decision rule: if a minimal repro reproduces against **bare** graphql-php (no Lighthouse), it's upstream → file/fix upstream and guard locally with `method_exists` until the constraint can be bumped. Precedent: `src/GraphQL.php:166` guards `getQueryComplexity` with the TODO to remove on a version bump (introduced with #2637); #2771 proposes the same pattern. Never bump the graphql-php floor in a minor to "clear" a guard — breaking for users on older 15.x (see `lighthouse-change-control`).

## The idea lifecycle in this repo

Each stage has real precedent:

1. **Origin** — production users' issues and upstream releases. Most CHANGELOG `Fixed`/`Added` entries trace to a user issue or a dependency change (e.g. #2735 null-`page` handling, #2769 class-finder support, #2766 Laravel 13 support). Good ideas come from real pain, not speculation.
2. **Design** — an issue/discussion frames the problem before code (labels `discussion`/`enhancement`).
3. **Experimental introduction** — ship flagged or explicitly experimental, off/neutral by default. Precedent: `@defer` is experimental (`src/lighthouse.php` `defer` comment); `validation_cache` defaults off; `shortcut_foreign_key_selection` defaults false.
4. **Hardening** — a shipped feature reveals a flaw, fixed forward. Precedent: file-based query cache (#2713) → atomic writes (#2716); schema cache write → temp-file+rename (#2703) → exclusive lock (`d5ea91d6`).
5. **Adoption as default OR retirement:**
   - *Adopted:* schema cache v2 proved out, then v1 was removed (#2321, `19ec1d1a`).
   - *Retired then revived:* `@cacheControl` reverted (`315392fd`) then re-added (#2136); `@namespaced` reverted (`198afbff`) then re-added (#2478). A revert is not always final — verify current `src/`.
   - *Retired for good:* implicit create/update flattening rejected in favor of `@spread` (`a6ae1e29`); the #961 resolver-efficiency rewrite reverted (`7cfd7922`).

Every retirement gets a tombstone in `lighthouse-failure-archaeology` so it is never silently re-fought.

## Retirement discipline

When you reject or abandon an idea, record it: symptom → root cause → why rejected → evidence, added to `lighthouse-failure-archaeology`. An undocumented dead end will be rediscovered and re-attempted, wasting the next session's time. The archaeology chronicle is a first-class deliverable, not an afterthought.

## When NOT to use this skill

- The concrete proof recipes (how to measure laziness, N+1, BC, races) → `lighthouse-proof-and-analysis-toolkit`.
- The settled outcomes to avoid re-fighting → `lighthouse-failure-archaeology`.
- Ratifying/gating the resulting change → `lighthouse-change-control`.
- Long-horizon problem selection → `lighthouse-research-frontier`.

## Provenance and maintenance

Verified 2026-07-07 against v6.68.0 (`master` @ a29ff8e). Re-verify with:

```bash
git show 19ec1d1a --stat        # schema cache v1 removed after v2 adopted
git log --oneline -- src/CacheControl | tail    # @cacheControl retire→revive
grep -n "method_exists" src/GraphQL.php          # upstream-vs-local guard pattern
grep -n "defer\|validation_cache\|shortcut_foreign_key" src/lighthouse.php  # experimental defaults
```
