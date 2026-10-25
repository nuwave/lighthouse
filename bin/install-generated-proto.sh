#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

rm -rf src/Tracing/FederatedTracing/Proto
mv proto-tmp/Nuwave/Lighthouse/Tracing/FederatedTracing/Proto src/Tracing/FederatedTracing/Proto
rm -rf proto-tmp
