#!/usr/bin/env bash
set -euo pipefail

cd /opt/oc
VER="$(php -r '$c=require "app/Config.php"; echo $c["app_version"] ?? "v0";')"
exec ./scripts/master-backup-vps.sh "$VER"
