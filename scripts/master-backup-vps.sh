#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="/opt/oc"
BACKUP_ROOT="${APP_ROOT}/.oc_master_backups"
PHP_BIN="${PHP_BIN:-}"
MYSQLDUMP_BIN="${MYSQLDUMP_BIN:-mysqldump}"

resolve_php_bin() {
  if [[ -n "${PHP_BIN}" && -x "$(command -v "${PHP_BIN}" 2>/dev/null || true)" ]]; then
    echo "${PHP_BIN}"
    return 0
  fi
  for candidate in php8.5 php php8.4 php8.3; do
    if command -v "${candidate}" >/dev/null 2>&1; then
      echo "${candidate}"
      return 0
    fi
  done
  return 1
}

require_cmd() {
  local cmd="$1"
  if ! command -v "${cmd}" >/dev/null 2>&1; then
    echo "ERROR: required command not found: ${cmd}" >&2
    exit 1
  fi
}

if ! PHP_BIN="$(resolve_php_bin)"; then
  echo "ERROR: php binary not found (tried php8.5/php/php8.4/php8.3)" >&2
  exit 1
fi

if [[ ! -d "${APP_ROOT}" ]]; then
  echo "ERROR: app root not found: ${APP_ROOT}" >&2
  exit 1
fi

require_cmd "${MYSQLDUMP_BIN}"
require_cmd tar
require_cmd gzip
require_cmd sha256sum

mkdir -p "${BACKUP_ROOT}"
chmod 700 "${BACKUP_ROOT}"
chown root:root "${BACKUP_ROOT}"

version_arg="${1:-}"
if [[ -n "${version_arg}" ]]; then
  version="${version_arg#v}"
  version="v${version}"
else
  version="$(grep -Eo "const APP_VERSION = 'v[0-9]+';" "${APP_ROOT}/index.php" | sed -E "s/.*'(v[0-9]+)'.*/\\1/" | head -n1 || true)"
fi

if [[ -z "${version}" ]]; then
  echo "ERROR: version not found. Pass as arg: v255" >&2
  exit 1
fi

ts="$(date -u +%Y%m%dT%H%M%SZ)"
backup_dir="${BACKUP_ROOT}/${version}_${ts}"
tmp_backup_dir="${BACKUP_ROOT}/.${version}_${ts}.tmp"
if [[ -e "${backup_dir}" || -e "${tmp_backup_dir}" ]]; then
  echo "ERROR: backup target already exists: ${backup_dir}" >&2
  exit 1
fi
mkdir -p "${tmp_backup_dir}"
chmod 700 "${tmp_backup_dir}"

cleanup_tmp() {
  local rc=$?
  if [[ $rc -ne 0 && -d "${tmp_backup_dir}" ]]; then
    rm -rf "${tmp_backup_dir}"
  fi
  exit $rc
}
trap cleanup_tmp EXIT

readarray -t dbcfg < <(${PHP_BIN} -r '
$c = include "/opt/oc/app/Config.php";
$db = is_array($c["sharing"]["db"] ?? null) ? $c["sharing"]["db"] : [];
$database = trim((string)($db["database"] ?? ""));
$username = trim((string)($db["username"] ?? ""));
$password = (string)($db["password"] ?? "");
$allowEmptyPassword = !empty($db["allow_empty_password"]) ? "1" : "0";
$host = trim((string)($db["host"] ?? "localhost"));
$port = (string)($db["port"] ?? 3306);
if ($database === "") {
    $database = "oc_masterdata";
}
echo $database . "\n";
echo $username . "\n";
echo $password . "\n";
echo $host . "\n";
echo $port . "\n";
echo $allowEmptyPassword . "\n";
')

DB_NAME="${dbcfg[0]:-}"
DB_USER="${dbcfg[1]:-oc_app}"
DB_PASS="${dbcfg[2]:-}"
DB_HOST="${dbcfg[3]:-localhost}"
DB_PORT="${dbcfg[4]:-3306}"
DB_ALLOW_EMPTY_PASSWORD="${dbcfg[5]:-0}"

if [[ -z "${DB_NAME}" ]]; then
  echo "ERROR: invalid DB name extracted from config" >&2
  exit 1
fi

code_archive="${tmp_backup_dir}/code_${version}.tar.gz"
db_archive="${tmp_backup_dir}/db_${DB_NAME}.sql.gz"
manifest_file="${tmp_backup_dir}/manifest.json"
guide_file="${tmp_backup_dir}/DISASTER_RECOVERY_AI.md"
dump_error_log="${tmp_backup_dir}/db_dump_error.log"

tar \
  --exclude=".oc_master_backups" \
  --exclude="tmp" \
  --exclude="demo" \
  --exclude="bench" \
  -czf "${code_archive}" -C /opt oc

dump_ok=0
if [[ -n "${DB_PASS}" || "${DB_ALLOW_EMPTY_PASSWORD}" == "1" ]]; then
  if MYSQL_PWD="${DB_PASS}" "${MYSQLDUMP_BIN}" \
    --host="${DB_HOST}" \
    --port="${DB_PORT}" \
    --user="${DB_USER}" \
    --single-transaction \
    --quick \
    --routines \
    --events \
    --triggers \
    "${DB_NAME}" 2>"${dump_error_log}" | gzip -9 > "${db_archive}"; then
    dump_ok=1
  fi
fi

if [[ "${dump_ok}" -eq 0 && ("${DB_HOST}" == "localhost" || "${DB_HOST}" == "127.0.0.1") ]]; then
  if "${MYSQLDUMP_BIN}" \
    --protocol=socket \
    --user=root \
    --single-transaction \
    --quick \
    --routines \
    --events \
    --triggers \
    "${DB_NAME}" 2>"${dump_error_log}" | gzip -9 > "${db_archive}"; then
    dump_ok=1
  fi
fi

if [[ "${dump_ok}" -eq 0 ]]; then
  echo "ERROR: db dump failed. Inspect ${dump_error_log}" >&2
  exit 2
fi

if [[ ! -s "${db_archive}" ]]; then
  echo "ERROR: db archive is empty: ${db_archive}" >&2
  exit 2
fi

if ! gzip -t "${db_archive}" >/dev/null 2>&1; then
  echo "ERROR: db archive is not a valid gzip stream: ${db_archive}" >&2
  exit 2
fi

db_uncompressed_bytes="$(gzip -cd "${db_archive}" | wc -c | tr -d ' ')"
if [[ "${db_uncompressed_bytes}" -lt 200 ]]; then
  echo "ERROR: db dump looks truncated (${db_uncompressed_bytes} bytes uncompressed)" >&2
  exit 2
fi

code_sha="$(sha256sum "${code_archive}" | awk '{print $1}')"
db_sha="$(sha256sum "${db_archive}" | awk '{print $1}')"

cat > "${manifest_file}" <<MAN
{
  "type": "oc-master-backup",
  "version": "${version}",
  "created_at_utc": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "backup_dir": "${backup_dir}",
  "code_archive": "$(basename "${code_archive}")",
  "code_sha256": "${code_sha}",
  "db_archive": "$(basename "${db_archive}")",
  "db_name": "${DB_NAME}",
  "db_sha256": "${db_sha}",
  "app_root": "${APP_ROOT}",
  "site_url": "https://oc.colortr.com"
}
MAN

cat > "${guide_file}" <<GUIDE
# OC Master Backup Recovery Guide (AI-Ready)

## Scope
- App root: ${APP_ROOT}
- Backup root: ${BACKUP_ROOT}
- Backup folder: ${backup_dir}
- Version: ${version}
- Site URL: https://oc.colortr.com

## Files in this backup
- Code archive: $(basename "${code_archive}")
- Database dump: $(basename "${db_archive}")
- Manifest: $(basename "${manifest_file}")

## Restore steps
1. Put service in maintenance mode (or stop PM2 process bitaxe-oc).
2. Backup current broken state before overwrite.
3. Restore code:
   - tar -xzf $(basename "${code_archive}") -C /
4. Restore database:
   - gunzip -c $(basename "${db_archive}") | mysql ${DB_NAME}
5. Ensure permissions:
   - chown -R root:root ${APP_ROOT}
   - chmod 700 ${APP_ROOT}/storage ${APP_ROOT}/tmp ${BACKUP_ROOT}
6. Restart runtime:
   - pm2 restart bitaxe-oc
7. Validate:
   - curl -I https://oc.colortr.com
   - Open share pages and admin panel.

## Notes
- Backup folder is blocked at Nginx level and kept hidden under dot-path.
- Keep this folder root-only.
GUIDE

cat > "${BACKUP_ROOT}/MASTER_POINTER.txt" <<PTR
MASTER_VERSION=${version}
MASTER_BACKUP_DIR=${backup_dir}
MASTER_SET_AT_UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)
SITE_URL=https://oc.colortr.com
PTR

chmod 600 "${code_archive}" "${db_archive}" "${manifest_file}" "${guide_file}" "${BACKUP_ROOT}/MASTER_POINTER.txt"
mv "${tmp_backup_dir}" "${backup_dir}"
chmod 700 "${backup_dir}"

code_archive_final="${backup_dir}/$(basename "${code_archive}")"
db_archive_final="${backup_dir}/$(basename "${db_archive}")"

trap - EXIT

printf "MASTER_BACKUP_OK=1\n"
printf "MASTER_VERSION=%s\n" "${version}"
printf "MASTER_BACKUP_DIR=%s\n" "${backup_dir}"
printf "CODE_ARCHIVE=%s\n" "${code_archive_final}"
printf "DB_ARCHIVE=%s\n" "${db_archive_final}"
