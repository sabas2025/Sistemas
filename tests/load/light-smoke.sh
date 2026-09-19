#!/usr/bin/env bash
set -euo pipefail
BASE_URL="${HUB_BASE_URL:-https://hub.ctba.top/public}"
REQUESTS="${REQUESTS:-30}"
CONCURRENCY="${CONCURRENCY:-5}"

echo "Teste de carga leve não invasivo: $REQUESTS requests, concorrência $CONCURRENCY, URL $BASE_URL/index.php"
if command -v ab >/dev/null 2>&1; then
  ab -n "$REQUESTS" -c "$CONCURRENCY" "$BASE_URL/index.php" || true
elif command -v curl >/dev/null 2>&1; then
  seq "$REQUESTS" | xargs -n1 -P"$CONCURRENCY" -I{} curl -sk -o /dev/null -w "%{http_code} %{time_total}\n" "$BASE_URL/index.php"
else
  echo "Instale apache2-utils (ab) ou curl."
fi
