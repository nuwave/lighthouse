#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

proto=src/Tracing/FederatedTracing/reports.proto

curl --silent --show-error --fail --output "$proto" https://usage-reporting.api.apollographql.com/proto/reports.proto
sed -i 's/ \[(js_use_toArray) = true]//g' "$proto"
sed -i 's/ \[(js_preEncoded) = true]//g' "$proto"
sed -i '3 i option php_namespace = "Nuwave\\\\Lighthouse\\\\Tracing\\\\FederatedTracing\\\\Proto";' "$proto"
sed -i '4 i option php_metadata_namespace = "Nuwave\\\\Lighthouse\\\\Tracing\\\\FederatedTracing\\\\Proto\\\\Metadata";' "$proto"
