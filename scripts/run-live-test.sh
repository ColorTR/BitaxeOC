#!/usr/bin/env bash
set -euo pipefail

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
  echo "Usage: ./scripts/run-live-test.sh [--allow-live] [target_url]"
  echo "Default target_url: http://127.0.0.1:8000/bitaxe-oc/"
  echo "Env: SAFARI_WEBDRIVER_URL=http://127.0.0.1:4444"
  echo "Env: ALLOW_LIVE_TESTS=1 (live URL kilidini acmak icin)"
  exit 0
fi

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ALLOW_LIVE_FLAG=0
POSITIONAL=()
while [[ $# -gt 0 ]]; do
  case "$1" in
    --allow-live)
      ALLOW_LIVE_FLAG=1
      shift
      ;;
    *)
      POSITIONAL+=("$1")
      shift
      ;;
  esac
done
if [[ ${#POSITIONAL[@]} -gt 0 ]]; then
  set -- "${POSITIONAL[@]}"
else
  set --
fi

TARGET_URL="${1:-http://127.0.0.1:8000/bitaxe-oc/}"
DRIVER_URL="${SAFARI_WEBDRIVER_URL:-http://127.0.0.1:4444}"
DRIVER_PID=""

is_live_target_url() {
  local url="$1"
  local host
  host="$(printf '%s' "${url}" | sed -E 's#^[a-zA-Z]+://([^/:]+).*#\1#')"
  [[ -z "${host}" ]] && return 1
  case "${host}" in
    localhost|127.*|10.*|192.168.*) return 1 ;;
  esac
  if [[ "${host}" =~ ^172\.([1][6-9]|2[0-9]|3[0-1])\. ]]; then
    return 1
  fi
  return 0
}

if is_live_target_url "${TARGET_URL}" && [[ "${ALLOW_LIVE_FLAG}" != "1" && "${ALLOW_LIVE_TESTS:-0}" != "1" ]]; then
  echo "[error] Live target kilidi aktif: ${TARGET_URL}" >&2
  echo "[hint] Canliya bilincli kosmak icin --allow-live kullan veya ALLOW_LIVE_TESTS=1 ayarla." >&2
  exit 1
fi

cleanup() {
  if [[ -n "${DRIVER_PID}" ]]; then
    kill "${DRIVER_PID}" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

if ! curl -fsS "${DRIVER_URL}/status" >/dev/null 2>&1; then
  if [[ "${DRIVER_URL}" != "http://127.0.0.1:4444" ]]; then
    echo "[error] Auto-start only supports http://127.0.0.1:4444"
    echo "[error] Start your custom endpoint manually, then rerun."
    exit 1
  fi
  echo "[info] Safari WebDriver not running, starting on :4444 ..."
  safaridriver -p 4444 >/tmp/bitaxe_oc_safaridriver.log 2>&1 &
  DRIVER_PID=$!
  for _ in {1..50}; do
    if curl -fsS "${DRIVER_URL}/status" >/dev/null 2>&1; then
      break
    fi
    sleep 0.2
  done
fi

if ! curl -fsS "${DRIVER_URL}/status" >/dev/null 2>&1; then
  echo "[error] Safari WebDriver is not reachable at ${DRIVER_URL}"
  echo "[hint] Run once: safaridriver --enable"
  exit 1
fi

echo "[info] Running live test against: ${TARGET_URL}"
SAFARI_WEBDRIVER_URL="${DRIVER_URL}" node "${ROOT_DIR}/scripts/live-e2e-safari.js" "${TARGET_URL}"
