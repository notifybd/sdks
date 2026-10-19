#!/usr/bin/env bash
# Fails while a placeholder that must not ship is still present.
set -euo pipefail
cd "$(dirname "$0")/.."

if grep -rIn --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=.venv --exclude-dir=dist \
     --exclude-dir=.git --exclude-dir=docs --exclude=RELEASING.md --exclude=check-placeholders.sh \
     --exclude=README.md "notify\.example" packages; then
  echo "Replace the placeholder API URL (notify.example) before releasing." >&2
  exit 1
fi
