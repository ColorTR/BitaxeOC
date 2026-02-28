# RUN 05 - WS-04 Secrets/Config Drift + WS-05 DB Privilege Review

Date (UTC): 2026-02-24
Workstreams: WS-04 (P0), WS-05 (P1)

## WS-04 Config Precedence / Drift

### Checks

- Local effective config (`app/Config.php` require)
- VPS effective config (`/opt/oc/app/Config.php` require)
- VPS secret file (`/opt/oc/app/Config.secret.php`)
- PM2/env BITAXE_* exposure check

### Evidence

Remote config evaluation:

```json
{
  "secret_sharing_driver": "db",
  "secret_logging_driver": "db",
  "secret_db_password_present": false,
  "effective_sharing_driver": "file",
  "effective_logging_driver": "file",
  "effective_transient_store": "file"
}
```

Interpretation:
- `Config.secret.php` requests DB mode, but DB password is empty.
- `Config.php` safeguard (`dbIsConfigured`) auto-falls back to `file` drivers.

### WS-04 Finding

- Config intent/runtime mismatch: DB-backed sharing/logging/transient store are not active in production runtime despite secret file declaring db driver.

## WS-05 DB Privilege / Schema

### Evidence

MariaDB snapshot:
- DB: `oc_masterdata`
- User: `oc_app@localhost`
- Grants:
  - `GRANT ALL PRIVILEGES ON oc_masterdata.* TO oc_app@localhost`

Table inventory present:
- `share_records`, `usage_events`, `security_events_unit_vps`, plus unit-test tables.

Cross-check token write path:
- token created via share API (`d6441f9cb39e2a333976b3df`)
- DB rows for token: `0`
- file path exists: `/opt/oc/storage/shares/d6/d6441f9cb39e2a333976b3df.json`

### WS-05 Findings

- Privilege model is broad (`ALL PRIVILEGES`) vs least-privilege target.
- Runtime currently writes to file storage, not DB tables, due WS-04 mismatch.

## Exit Criteria

WS-04:
- single authoritative runtime secret path: FAIL (drift)
- no plaintext prod secrets in repo: PARTIAL (password fields empty, but static fallback secrets still present)

WS-05:
- least privilege enforced: FAIL
- critical paths indexed: PASS (share_records/usage_events index set exists)
