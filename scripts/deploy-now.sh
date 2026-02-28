#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"

LEGACY_DEPLOY="${ROOT_DIR}/scripts/deploy_bitaxe_oc.sh"
VPS_DEPLOY="${ROOT_DIR}/scripts/deploy_oc_vps.sh"

if [[ -x "${VPS_DEPLOY}" ]]; then
  exec "${VPS_DEPLOY}" "$@"
fi

if [[ -x "${LEGACY_DEPLOY}" ]]; then
  exec "${LEGACY_DEPLOY}" "$@"
fi

cat >&2 <<'EOF'
ERROR: Deploy wrapper script not found in workspace root.
Expected one of:
  - scripts/deploy_oc_vps.sh
  - scripts/deploy_bitaxe_oc.sh

This workspace looks trimmed to app-only layout (likely after project merge/cleanup).
Use server-side deployment flow on VPS (/opt/oc) or restore root /scripts deploy tools.
EOF
exit 2
