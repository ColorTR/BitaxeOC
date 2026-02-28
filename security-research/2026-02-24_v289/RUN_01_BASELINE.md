# RUN 01 - Baseline Security Checks

Date (UTC): 2026-02-24  
Operator: Codex  
Target: https://oc.colortr.com/

## Scope Started

- WS-01 Ops panel auth/session baseline
- WS-02 upload/parser baseline guard checks
- WS-03 share/API abuse baseline checks
- WS-04 secrets/config drift baseline

## Commands Executed

1. `php /Users/colortr/Downloads/aaa_fork/bitaxe-oc/scripts/backend-smoke.php`
2. `php /Users/colortr/Downloads/aaa_fork/bitaxe-oc/scripts/backend-unit.php`
3. `node /Users/colortr/Downloads/aaa_fork/bitaxe-oc/scripts/live-http-audit.js https://oc.colortr.com/`
4. `BITAXE_OPS_USER='reis' BITAXE_OPS_PASS='***' node /Users/colortr/Downloads/aaa_fork/bitaxe-oc/scripts/ops-panel-audit.js https://oc.colortr.com/`
5. Pattern sweep:
   - `rg` for hardcoded secrets and dangerous execution paths in active app files.

## Results

### Backend Smoke
- PASS 3/3

### Backend Unit
- PASS 11/11
- DB-specific checks SKIP when `BITAXE_TEST_DB_DSN` is not set (expected in current local run context).

### Live HTTP Audit
- PASS 81/81
- Key observations:
  - security headers present
  - share API replay/origin checks passing
  - anonymous protected paths blocked

### Ops Panel Audit
- PASS 6/9, SKIP 3/9
- Authenticated flow checks in this script still marked `credentials_not_verified (login_not_completed)`; endpoint protection checks pass.

### Pattern Sweep
- No plaintext API key/private key literal found in active app files.
- Noted review hotspots:
  - `index.php` CSP currently includes `'unsafe-inline'` and `'unsafe-eval'` (hardening candidate, WS-07).
  - `ops-panel.php` uses `@exec(...)` for process metrics collection (WS-01 review item, currently constrained command).

## Initial Risk Notes (Not Final Findings)

1. WS-07 candidate: CSP tightening opportunity without UI break risk analysis yet.
2. WS-01 candidate: process-metrics shell execution path should be reviewed for hardening invariants (already fixed-command style, but still privileged surface).
3. WS-04 candidate: backup script and runtime secret strategy need alignment proof under env-only secret model.

## Next Run

- RUN 02 will deep-dive WS-01 (session/remember-cookie threat model + auth flow repro matrix).
