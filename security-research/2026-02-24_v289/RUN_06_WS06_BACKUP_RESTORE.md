# RUN 06 - WS-06 Backup/Restore Disaster Drill

Date (UTC): 2026-02-24
Workstream: WS-06 (P1)

## Scope

- Backup root: `/opt/oc/.oc_master_backups`
- Sample backup: `v289_20260224T105928Z`
- Backup script: `/opt/oc/scripts/master-backup-vps.sh`

## Integrity Validation

Manifest vs archives checksum:
- code sha256 matches manifest
- db sha256 matches manifest

Archive sanity:
- code archive contains app tree (`oc/`)
- db dump has valid MariaDB dump headers and DDL content

## Controlled Script Drill

Executed:
- `/opt/oc/scripts/master-backup-vps.sh v289dry2`

Observed:
- `mysqldump: Access denied for user 'oc_app'@'localhost' (using password: NO)`
- exit code: `2`

Cleanup:
- test artifact `v289dry2_*` removed.

## Findings

- Backup script currently depends on DB password from `Config.secret.php`.
- With current runtime secret state (empty password), backup script fails DB dump creation.
- Existing old backups remain valid, but future automated/manual backups are at risk of incomplete DB capture unless credential source is fixed.

## WS-06 Exit Criteria

- reproducible restore with verified integrity: PARTIAL (historical backup set valid)
- reproducible backup from current runtime config: FAIL
- documented RTO/RPO: PARTIAL (guide exists, but no measured timed drill yet)
