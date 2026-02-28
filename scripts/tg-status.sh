#!/usr/bin/env bash
set -euo pipefail

cd /opt/oc

php -r '
$c = require "app/Config.php";
echo "version=" . ($c["app_version"] ?? "-") . PHP_EOL;
echo "sharing_driver=" . (($c["sharing"]["driver"] ?? "-")) . PHP_EOL;
echo "logging_driver=" . (($c["logging"]["driver"] ?? "-")) . PHP_EOL;
'

echo "nginx=$(systemctl is-active nginx 2>/dev/null || echo unknown)"
echo "bridge=$(systemctl is-active oc-tg-bridge 2>/dev/null || echo unknown)"
echo "db=$(systemctl is-active mariadb 2>/dev/null || systemctl is-active mysql 2>/dev/null || echo unknown)"
echo "---"
df -h /opt/oc | tail -n 1
