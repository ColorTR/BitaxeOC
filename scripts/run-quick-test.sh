#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HOST="${1:-127.0.0.1}"
PORT="${2:-18080}"
BASE_URL="http://${HOST}:${PORT}"
PHP_SERVER_PID=""

cleanup() {
  if [[ -n "${PHP_SERVER_PID}" ]]; then
    kill "${PHP_SERVER_PID}" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

wait_for_http() {
  local url="$1"
  for _ in {1..50}; do
    if curl -fsS "${url}" >/dev/null 2>&1; then
      return 0
    fi
    sleep 0.2
  done
  return 1
}

if ! curl -fsS "${BASE_URL}/" >/dev/null 2>&1; then
  if ! command -v php >/dev/null 2>&1; then
    echo "[error] php bulunamadi. Quick test icin php CLI gerekli."
    exit 1
  fi
  echo "[info] local php server baslatiliyor: ${BASE_URL}"
  php -S "${HOST}:${PORT}" -t "${ROOT_DIR}" >/tmp/bitaxe_oc_quick_php.log 2>&1 &
  PHP_SERVER_PID=$!
  if ! wait_for_http "${BASE_URL}/"; then
    echo "[error] local php server ayaga kalkmadi"
    exit 1
  fi
fi

echo "[info] Quick Phase 1/4: PHP lint"
"${ROOT_DIR}/scripts/php-lint.sh"

echo "[info] Quick Phase 2/4: Backend unit + smoke"
php "${ROOT_DIR}/scripts/backend-unit.php"
php "${ROOT_DIR}/scripts/backend-smoke.php"

echo "[info] Quick Phase 3/4: Analyzer fixture matrix"
php "${ROOT_DIR}/scripts/analyzer-fixtures.php"

echo "[info] Quick Phase 4/4: Local API sanity"
status_share="$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/api/share.php?share=ffffffffffffffffffffffffffffffff")"
if [[ "${status_share}" != "404" ]]; then
  echo "[error] share API unknown token status beklenen 404, gelen=${status_share}"
  exit 1
fi
status_analyze="$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/api/analyze.php")"
if [[ "${status_analyze}" != "405" ]]; then
  echo "[error] analyze API GET status beklenen 405, gelen=${status_analyze}"
  exit 1
fi

echo "[info] Quick Phase 4.5/4: Ops panel structural audit"
node "${ROOT_DIR}/scripts/ops-panel-audit.js" "${BASE_URL}/"

echo "[info] QUICK TEST DONE"
