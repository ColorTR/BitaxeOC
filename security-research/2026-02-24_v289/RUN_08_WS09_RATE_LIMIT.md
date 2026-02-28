# RUN 08 - WS-09 Rate Limit + Anti-Automation Resilience

Date (UTC): 2026-02-24
Workstream: WS-09 (P1)

## Share Create Burst

Target: `POST /api/share.php?action=create`

Burst test result:
- attempts 1..20: `201/200`
- attempt 21: `429`
- body: `{"ok":false,"error":"Cok fazla istek. Lutfen biraz sonra tekrar deneyin."}`

## Ops Login Throttle

Remote loop against backend (`127.0.0.1:3001`, Host `oc.colortr.com`) with wrong credentials:
- rate limit hit at attempt `6`

Result line:
- `ops_rate_limit_hit_attempt=6`

## Findings

- Share API rate-limit threshold enforced as designed.
- Ops login throttle path enforced under repeated invalid attempts.

## Exit Criteria

- protective throttling works: PASS
- legitimate usage false-lockout not deeply profiled in this run: PARTIAL
