# Final Consolidated Security Research Report (12 Workstreams)

Date: 2026-02-24
Target: https://oc.colortr.com/
Research bundle: `/Users/colortr/Downloads/aaa_fork/bitaxe-oc/security-research/2026-02-24_v289`

## A) Executive Summary

Completed coverage:
- WS-01..WS-12 executed at baseline depth with evidence logs.

Risk posture (this run):
- Critical: 0
- High: 1
- Medium: 3
- Low: 3

Top priorities:
1. Fix backup script DB credential source mismatch (P0)
2. Resolve config drift: intended DB mode currently falling back to file mode (P1)
3. Reduce DB privileges (`oc_app`) from `ALL PRIVILEGES` to least privilege (P1)
4. Plan CSP tightening (`unsafe-inline`/`unsafe-eval`) without UI break (P1/P2)

## B) Workstream Status Matrix

- WS-01 Auth/session threat model: Completed (PASS)
- WS-02 CSV parser fuzzing: Completed (PASS)
- WS-03 Share token/replay/origin/etag: Completed (PASS)
- WS-04 Secrets/config drift: Completed (FAIL - drift found)
- WS-05 DB privilege/index review: Completed (PARTIAL - privilege issue)
- WS-06 Backup/restore drill: Completed (FAIL - backup script db dump path)
- WS-07 Nginx/CSP hardening review: Completed (PARTIAL - CSP still permissive)
- WS-08 Supply chain review: Completed (PARTIAL - external trust chain and pending kernel updates)
- WS-09 Rate-limit resilience: Completed (PASS)
- WS-10 Monitoring baseline: Completed (PARTIAL)
- WS-11 Security automation pipeline: Completed (FAIL - not implemented)
- WS-12 Agent-config security: Completed (PASS)

## C) Findings (Prioritized)

### P0 / High

- FR-006 (WS-06): Backup script DB dump currently fails with `oc_app` password missing (`using password: NO`), exit code 2.

### P1 / Medium

- FR-004 (WS-04): Config intent/runtime mismatch. `Config.secret.php` sets db drivers, effective runtime falls back to file.
- FR-005 (WS-05): `oc_app@localhost` has `ALL PRIVILEGES` on `oc_masterdata`.
- FR-009 (WS-11): Security CI pipeline missing (only php lint exists).

### P2 / Low

- FR-001 (WS-07): CSP includes `unsafe-inline` + `unsafe-eval`.
- FR-007 (WS-10): No explicit scheduled master-backup automation observed (`crontab` empty).
- FR-008 (WS-08): Kernel package updates pending.

## D) Key Evidence Pointers

- Baseline: `RUN_01_BASELINE.md`
- Auth/session deep dive: `RUN_02_WS01_AUTH_SESSION.md`
- Share API matrix: `RUN_03_WS03_SHARE_API.md`
- CSV fuzz matrix: `RUN_04_WS02_CSV_FUZZ.md`
- Config+DB review: `RUN_05_WS04_WS05_CONFIG_DB.md`
- Backup drill: `RUN_06_WS06_BACKUP_RESTORE.md`
- Infra/supply: `RUN_07_WS07_WS08_INFRA_SUPPLY.md`
- Rate-limit: `RUN_08_WS09_RATE_LIMIT.md`
- Monitoring/pipeline/agent: `RUN_09_WS10_WS11_WS12.md`

## E) Recommended Patch Sequence (No code changes applied yet)

1. Backup reliability patch (P0)
- Source DB creds from secure runtime env/service unit, not static empty config fields.
- Fail fast with explicit preflight (`db password missing`) before archive creation.
- Add post-backup integrity gate (non-empty SQL dump + checksum + row count sanity).

2. Config drift closure (P1)
- Decide canonical mode: `db` or `file`.
- If DB mode intended, configure password via runtime secret and verify effective config == intended config.
- If file mode intended, update secret/config docs to remove db-mode ambiguity.

3. DB least privilege (P1)
- Replace `ALL PRIVILEGES` with minimum required grants for active mode.
- Separate users for share/logging/security-event writes if DB mode is enabled.

4. Security pipeline (P1)
- Add PR/nightly workflows for semgrep + gitleaks + trivy + zap baseline + live security smoke.
- Define fail thresholds and artifact retention policy.

5. CSP hardening roadmap (P2)
- Remove `unsafe-eval` first (measure regressions), then phase out `unsafe-inline` with nonce/hash strategy.

## F) Notes

- No production code or server config was modified during this research run.
- One temporary backup dry-run artifact created during validation was removed.
