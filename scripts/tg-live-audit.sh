#!/usr/bin/env bash
set -euo pipefail

cd /opt/oc
node ./scripts/live-http-audit.js https://oc.colortr.com/
node ./scripts/ops-panel-audit.js https://oc.colortr.com/
