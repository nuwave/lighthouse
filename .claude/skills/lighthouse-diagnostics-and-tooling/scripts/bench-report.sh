#!/usr/bin/env bash
# bench-report.sh — run PHPBench and save a timestamped aggregate report.
#
# Purpose: capture a benchmark baseline before a change and again after, so perf
# claims rest on numbers from the SAME machine. Saves each run to a dated file for
# later comparison. Never trust a delta smaller than the report's rstdev.
#
# Usage:
#   scripts/bench-report.sh [label]
#   scripts/bench-report.sh before
#   scripts/bench-report.sh after
# Output: ./bench-reports/<UTC-timestamp>-<label>.txt
#
# Docker users: this wraps `make bench` (which runs phpbench in the php container).
# Native users: set BENCH="vendor/bin/phpbench run --report=aggregate".
#
# Verified with `bash -n` on 2026-07-07; NOT executed in the authoring environment
# (no vendor/). phpbench config: phpbench.json (bootstrap vendor/autoload.php, path benchmarks).
set -euo pipefail

LABEL="${1:-run}"
BENCH="${BENCH:-make bench}"
OUTDIR="bench-reports"
mkdir -p "$OUTDIR"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUT="$OUTDIR/${STAMP}-${LABEL}.txt"

echo "Running: $BENCH  (label: $LABEL)"
# tee keeps the report on screen and on disk.
$BENCH 2>&1 | tee "$OUT"
echo
echo "Saved report to $OUT"
echo "Compare two runs with: diff <(cat $OUTDIR/<before>.txt) <(cat $OUTDIR/<after>.txt)"
echo "Reminder: only trust deltas larger than the rstdev column; same machine only."
