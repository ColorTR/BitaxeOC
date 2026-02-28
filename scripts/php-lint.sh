#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PHP_BIN="$(command -v php || true)"
if [[ -z "${PHP_BIN}" && -x "/opt/homebrew/bin/php" ]]; then
  PHP_BIN="/opt/homebrew/bin/php"
fi

if [[ -z "${PHP_BIN}" ]]; then
  echo "[error] php CLI bulunamadi."
  echo "[hint] zsh -lc 'php -v' ile kontrol edin."
  exit 1
fi

echo "[info] php binary: ${PHP_BIN}"
"${PHP_BIN}" -v | head -n 2

TOTAL_COUNT=0
FAIL_COUNT=0
while IFS= read -r file; do
  TOTAL_COUNT=$((TOTAL_COUNT + 1))
  if ! "${PHP_BIN}" -l "${file}" >/dev/null 2>&1; then
    echo "[fail] ${file}"
    "${PHP_BIN}" -l "${file}" || true
    FAIL_COUNT=$((FAIL_COUNT + 1))
  fi
done < <(find "${ROOT_DIR}" -type f -name "*.php" | sort)

if [[ "${TOTAL_COUNT}" -eq 0 ]]; then
  echo "[warn] Lint edilecek .php dosyasi yok."
  exit 0
fi

if [[ "${FAIL_COUNT}" -gt 0 ]]; then
  echo "[result] FAIL ${FAIL_COUNT}/${TOTAL_COUNT}"
  exit 1
fi

echo "[result] ALL_OK ${TOTAL_COUNT} files"
