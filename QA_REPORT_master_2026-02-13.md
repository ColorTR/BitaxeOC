# Bitaxe-OC Master QA Report (2026-02-13)

## Scope
- Target: `https://oc.colortr.com/`
- Browser automation: Safari WebDriver
- Goal: Baseline test -> strengthen tests -> rerun -> verify no regressions

## Baseline (Before Test-Suite Fixes)
Run command:
- `/Users/colortr/Downloads/aaa_fork/bitaxe-oc/scripts/run-live-test.sh https://oc.colortr.com/`

Result:
- `PASS 11/13`, `FAIL 2/13`

Failures:
1. Smoke test expected old button text (`START ANALYSIS`), while live UI uses `RECALCULATE ANALYSIS`.
2. Merge override test used hardcoded table column indexes; table order changed (Hashrate moved earlier), causing `null` lookup.

Root cause:
- Test brittleness (not app defect).

## Improvements Applied
File updated:
- `/Users/colortr/Downloads/aaa_fork/bitaxe-oc/scripts/live-e2e-safari.js`

Changes:
1. Made process button label assertion resilient to current wording (`ANALYSIS/ANALIZ`).
2. Rewrote merge test row parser to use dynamic column lookup via `data-sort-key`.
3. Added security checks:
   - Anonymous access to `api/analyze.php` blocked.
   - `ops-panel.php` includes `noindex` robots directive.
4. Added sample preview lifecycle test:
   - sample button visible only on initial screen,
   - sample excluded from file-manager list,
   - sample replaced cleanly after real upload.
5. Added filter controls test:
   - dynamic min/max range bounds,
   - input->range sync,
   - range->input sync,
   - clamp/sanitize on blur,
   - table filter actually reduces rows.
6. Added chart tooltip test:
   - tooltip callback exists and returns hashrate value.
7. Added pinned data-quality panel test:
   - user-pinned state persists after recalculation,
   - countdown remains hidden while pinned.

## Final Retest (After Improvements)
Run command:
- `/Users/colortr/Downloads/aaa_fork/bitaxe-oc/scripts/run-live-test.sh https://oc.colortr.com/`

Result:
- `PASS 19/19`, `FAIL 0/19`

Executed checks:
1. HTTP headers and availability
2. API anonymous access blocked
3. Ops panel noindex
4. Initial UI smoke
5. Process without file alert
6. Single CSV + countdown auto-close
7. Non-CSV skip validation
8. Max file count validation
9. Per-file size limit validation
10. Total upload size validation
11. Process while files loading validation
12. CSV row truncation (7000)
13. Merge master override at same V/F
14. UI controls (remove/view/language)
15. Sample preview lifecycle and replacement
16. Filter slider/input sync and clamp
17. Chart tooltip hashrate callback
18. Data quality pinned behavior
19. Export actions (HTML/JPEG) warning-free

## Extra Live Performance Snapshots
- `index`: status 200, `253393 B`, TTFB ~`0.547 s`, total ~`0.549 s`
- `sample CSV`: status 200, `16323 B`, TTFB ~`0.346 s`, total ~`0.346 s`
- `chart.umd.min.js`: status 200, `208522 B`, TTFB ~`0.488 s`, total ~`0.490 s`

## Security Header Spot Checks
Verified on live responses:
- `Content-Security-Policy`
- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Strict-Transport-Security`
- `Referrer-Policy`
- `Permissions-Policy`

## Gaps / Constraints
- Local PHP CLI is not installed in this environment (`php: command not found`), so PHP lint/unit checks could not be executed locally.
- Live API endpoint currently returns `403` for anonymous direct requests, which is acceptable and was asserted as expected security posture.

## Conclusion
- No functional regression found on live master.
- Test coverage significantly expanded and stabilized.
- Current live flow is stable on Safari with full pass (`19/19`).
