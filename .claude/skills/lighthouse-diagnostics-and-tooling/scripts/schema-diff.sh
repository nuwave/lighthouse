#!/usr/bin/env bash
# schema-diff.sh — prove a change does (or does not) alter the printed GraphQL schema.
#
# Purpose: the "no breaking changes for schema consumers" rule requires the printed
# SDL to be byte-identical for non-breaking work. This captures the schema at two git
# refs and diffs them. An empty diff means the schema surface is unchanged.
#
# Usage (run from a Laravel APP that uses Lighthouse, or adapt PHPUNIT path for a fixture app):
#   scripts/schema-diff.sh <ref-before> <ref-after>
#   scripts/schema-diff.sh master HEAD
#
# Assumptions:
#   - `php artisan lighthouse:print-schema` works in the current project (needs vendor/ + a schema).
#   - You are in a clean working tree (the script checks out refs).
#   - Docker users: prefix the artisan call with `docker compose run --rm php`.
#
# This script was syntax-checked with `bash -n` on 2026-07-07. It was NOT executed
# in the authoring environment (no vendor/). Review before running; it moves git refs.
set -euo pipefail

BEFORE="${1:?usage: schema-diff.sh <ref-before> <ref-after>}"
AFTER="${2:?usage: schema-diff.sh <ref-before> <ref-after>}"
ARTISAN="${ARTISAN:-php artisan}"   # override, e.g. ARTISAN="docker compose run --rm php vendor/bin/artisan"
TMPDIR="$(mktemp -d)"
trap 'rm -rf "$TMPDIR"' EXIT

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Working tree is dirty. Commit or stash first." >&2
  exit 1
fi
START_REF="$(git symbolic-ref --quiet --short HEAD || git rev-parse HEAD)"
restore() { git checkout --quiet "$START_REF"; }
trap 'restore; rm -rf "$TMPDIR"' EXIT

for ref in "$BEFORE" "$AFTER"; do
  git checkout --quiet "$ref"
  # --sort makes the output order-stable so the diff reflects real changes, not ordering.
  $ARTISAN lighthouse:clear-schema-cache >/dev/null 2>&1 || true
  $ARTISAN lighthouse:print-schema --sort > "$TMPDIR/${ref//\//_}.graphql"
done

echo "Diff of printed schema: $BEFORE -> $AFTER"
if diff -u "$TMPDIR/${BEFORE//\//_}.graphql" "$TMPDIR/${AFTER//\//_}.graphql"; then
  echo "SCHEMA UNCHANGED (non-breaking for consumers)."
else
  echo "SCHEMA CHANGED — every difference must be traced to an UPGRADE.md entry (breaking) or justified as additive."
  exit 1
fi
