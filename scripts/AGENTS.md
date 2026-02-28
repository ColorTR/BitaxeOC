# AGENTS.md (bitaxe-oc/scripts)

This file defines test and QA execution standards.

## 1) Test Execution Order

For production-impacting changes, run in this order:
1. PHP lint (changed files first, then full critical files if needed)
2. `php scripts/backend-smoke.php`
3. `php scripts/backend-unit.php`
4. `node scripts/live-http-audit.js https://oc.colortr.com/`
5. `node scripts/ops-panel-audit.js https://oc.colortr.com/`
6. `ALLOW_LIVE_TESTS=1 ./scripts/run-master-test.sh --allow-live https://oc.colortr.com/`

## 2) Browser/Test Mode Policy

- Functional E2E baseline is Safari WebDriver (`live-e2e-safari.js`, `live-bench-safari.js`).
- Do not introduce Chrome-only test assumptions into acceptance criteria.

## 3) Ops Panel Audit Expectations

- `ops-panel.php` must return:
  - login page `200`
  - `ajax=server_status` unauthorized `401` for anonymous access
  - no-store + noindex protections

Auth-flow tests may be skipped if `BITAXE_OPS_USER/BITAXE_OPS_PASS` are not set.

## 4) Master Test Acceptance

Required target:
- Live E2E summary: all PASS
- Live bench summary: all PASS
- HTTP audit: no FAIL

If any critical test fails:
1. do not mark task complete
2. provide failing test name and likely root cause
3. fix and rerun

## 5) Test Artifact Discipline

- Test scripts may generate temporary exports during runtime checks.
- Keep repo/workspace clean of unintended generated artifacts after test completion.
- Do not remove user-created exports.

## 6) Release Checklist (QA Gate)

1. Run tests in defined order; do not skip a prior stage after changing backend/app logic.
2. Verify zero critical FAIL before deploy confirmation.
3. After deploy, run live audits again for regression detection.
4. Record compact release QA summary:
  - version
  - pass/fail/skip counts
  - known non-blocking skips

## 7) Incident Rollback Checklist (QA Gate)

1. Capture failing test IDs and timestamps before rollback.
2. After rollback, rerun minimum suite:
  - backend smoke
  - backend unit
  - live HTTP audit
  - ops panel audit
3. Mark rollback successful only if critical checks are green.
4. Keep incident QA note linked to restored version.
