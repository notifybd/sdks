#!/usr/bin/env bash
# Refreshes spec/openapi.json from the platform.
#
#   scripts/sync-spec.sh                       # from $NOTIFY_OPENAPI_URL (the platform's GET /openapi.json)
#   scripts/sync-spec.sh /path/to/openapi.json # from a local build (packages/contracts/dist/openapi.json)
set -euo pipefail

target="$(cd "$(dirname "$0")/.." && pwd)/spec/openapi.json"
source="${1:-${NOTIFY_OPENAPI_URL:-}}"
[ -n "$source" ] || { echo "Pass a file path or set NOTIFY_OPENAPI_URL" >&2; exit 1; }

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT
if [ -f "$source" ]; then cp "$source" "$tmp"; else curl -fsSL "$source" -o "$tmp"; fi

# Refuse anything that is not an OpenAPI document, so a bad download cannot overwrite the contract.
node -e 'const d=JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")); if(!d.openapi||!d.paths) process.exit(1)' "$tmp" \
  || { echo "Not an OpenAPI document" >&2; exit 1; }

cp "$tmp" "$target"
echo "Updated $target"
