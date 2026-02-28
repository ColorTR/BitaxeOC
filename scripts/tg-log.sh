#!/usr/bin/env bash
set -euo pipefail

echo "[oc-tg-bridge]"
journalctl -u oc-tg-bridge --no-pager -n 40
echo "---"
if [[ -f /opt/oc/storage/logs/app.log ]]; then
  echo "[app.log]"
  tail -n 60 /opt/oc/storage/logs/app.log
else
  echo "[app.log] bulunamadi"
fi
