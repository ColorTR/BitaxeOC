# Findings Register (Rolling)

Date start: 2026-02-24
Version baseline: v289

## Summary

- Critical: 0
- High: 1
- Medium: 3
- Low: 3
- Status: Investigation completed for WS-01..WS-12 baseline pass

## Entries

| ID | WS | Severity | Priority | Status | Title | Evidence |
|----|----|----------|----------|--------|-------|----------|
| FR-001 | WS-07 | Low | P2 | Open | CSP still allows `'unsafe-inline'` / `'unsafe-eval'` | `RUN_07_WS07_WS08_INFRA_SUPPLY.md` |
| FR-002 | WS-01 | Low | P2 | Open | Ops panel process metrics uses shell exec path; constrained but should stay tightly bounded | `RUN_01_BASELINE.md` |
| FR-003 | WS-04 | Medium | P1 | Closed (reframed) | Initial backup/secret alignment candidate superseded by FR-006 concrete failure | `RUN_06_WS06_BACKUP_RESTORE.md` |
| FR-004 | WS-04 | Medium | P1 | Open | Config drift: secret asks DB mode, effective runtime falls back to file mode | `RUN_05_WS04_WS05_CONFIG_DB.md` |
| FR-005 | WS-05 | Medium | P1 | Open | DB app user has broad `ALL PRIVILEGES` on app schema | `RUN_05_WS04_WS05_CONFIG_DB.md` |
| FR-006 | WS-06 | High | P0 | Open | Master backup script DB dump fails (`mysqldump 1045`), risking incomplete backups | `RUN_06_WS06_BACKUP_RESTORE.md` |
| FR-007 | WS-10 | Low | P2 | Open | No explicit scheduled master-backup job observed (`crontab` empty) | `RUN_09_WS10_WS11_WS12.md` |
| FR-008 | WS-08 | Low | P2 | Open | Kernel/security package updates pending on VPS | `RUN_07_WS07_WS08_INFRA_SUPPLY.md` |
| FR-009 | WS-11 | Medium | P1 | Open | Security automation pipeline missing (current CI is lint-only) | `RUN_09_WS10_WS11_WS12.md` |
