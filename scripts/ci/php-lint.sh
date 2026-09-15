#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
find "$ROOT" -path "$ROOT/storage" -prune -o -name '*.php' -print0 | while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done
echo "PHP lint OK"
