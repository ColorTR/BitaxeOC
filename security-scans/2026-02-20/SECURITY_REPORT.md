# Security Scan Report (No-Claude Pipeline)

Date: 2026-02-20
Target URL: https://oc.colortr.com
Source Path: /Users/colortr/Downloads/aaa_fork/bitaxe-oc

## Method
Because Shannon Lite requires Anthropic/Claude credentials, this scan used a credential-free pipeline:
- Semgrep (SAST): `returntocorp/semgrep`
- Gitleaks (secret scan): `zricethezav/gitleaks`
- Trivy (vuln/misconfig/secret): `aquasec/trivy`
- ZAP Baseline (DAST): `ghcr.io/zaproxy/zaproxy:stable`
- Nmap TLS/Header check: `instrumentisto/nmap`

Artifacts:
- `/Users/colortr/Downloads/aaa_fork/bitaxe-oc/security-scans/2026-02-20/semgrep.json`
- `/Users/colortr/Downloads/aaa_fork/bitaxe-oc/security-scans/2026-02-20/gitleaks.json`
- `/Users/colortr/Downloads/aaa_fork/bitaxe-oc/security-scans/2026-02-20/trivy.json`
- `/Users/colortr/Downloads/aaa_fork/bitaxe-oc/security-scans/2026-02-20/zap.json`
- `/Users/colortr/Downloads/aaa_fork/bitaxe-oc/security-scans/2026-02-20/zap.md`
- `/tmp/nmap-oc.txt`

---

## Executive Summary
- Critical remote exploit was **not** reproduced in this run.
- Most impactful issues are **hardening gaps** and **secret hygiene**.
- Public endpoint protections looked strong overall (sensitive internal paths returned 404).

Priority overview:
1. **High**: plaintext DB credentials in source file (`app/Config.secret.php`) 
2. **Medium**: CSP allows `unsafe-inline` and `unsafe-eval`, external scripts without SRI
3. **Medium/Low**: static asset responses miss some security headers due Nginx header inheritance
4. **Low**: Semgrep findings are likely false positives around `json_encode(...)` constants

---

## Findings

### 1) Plaintext secrets in repo-tracked config
Severity: **High**

Evidence:
- `/Users/colortr/Downloads/aaa_fork/bitaxe-oc/app/Config.secret.php:15`
- `/Users/colortr/Downloads/aaa_fork/bitaxe-oc/app/Config.secret.php:31`
- Gitleaks flagged 4 items total.

Impact:
- If repository/workspace backup leaks, attacker can obtain DB credentials.
- Even if web access is blocked, source-level compromise remains high impact.

Recommendation:
- Move DB password and salts to environment variables or root-only external file outside app tree.
- Rotate current DB password and salts.
- Keep only placeholders in `Config.secret.php`.

---

### 2) CSP permits risky script/style execution patterns
Severity: **Medium**

Evidence from ZAP:
- `CSP: script-src unsafe-eval`
- `CSP: script-src unsafe-inline`
- `CSP: style-src unsafe-inline`
- `Sub Resource Integrity Attribute Missing`

Impact:
- Raises XSS impact if any injection vector is found later.
- CDN script compromise risk is higher without SRI.

Recommendation:
- Phase out `unsafe-eval` and `unsafe-inline` (use nonces/hashes).
- Self-host critical JS/CSS where possible.
- Add SRI (`integrity` + `crossorigin`) to third-party scripts/styles if CDN stays.

---

### 3) Security headers missing on some static assets
Severity: **Medium/Low**

Evidence (ZAP warnings on favicon/manifest/vendor assets):
- Missing on some asset responses: `X-Content-Type-Options`, `Strict-Transport-Security`, `Permissions-Policy`, `CORP/COEP`

Likely cause:
- Nginx `location` block for static files sets `add_header Cache-Control ...`, which can override inherited headers unless re-declared there.

Recommendation:
- Re-add critical `add_header ... always;` directives inside static `location` block too.

---

### 4) Semgrep reflected-input warnings around PHP output
Severity: **Low (likely false positive)**

Evidence:
- 7 findings on `/Users/colortr/Downloads/aaa_fork/bitaxe-oc/index.php:604-616`
- These lines output server-side constants via `json_encode(...)` into JS.

Assessment:
- Current pattern appears controlled and encoded, likely safe.

Recommendation:
- Keep as-is, but add `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT` for defense-in-depth if desired.

---

## Positive Checks
- Internal sensitive paths are blocked from public web:
  - `https://oc.colortr.com/app/Config.secret.php` -> 404
  - `https://oc.colortr.com/storage/shares/` -> 404
  - `https://oc.colortr.com/.oc_master_backups/` -> 404
- TLS ciphers from Nmap were strong (`least strength: A`).
- Trivy reported no package-level vulnerability/misconfig results in this repo snapshot.

---

## Recommended Remediation Order
1. Secret rotation + move secrets out of source tree
2. Harden CSP + remove `unsafe-*` + add SRI
3. Fix static asset header inheritance in Nginx
4. Optional: tighten output encoding flags in PHP `json_encode`

---

## Note on Shannon
Shannon itself started successfully, but scan halted at pre-recon due invalid external API key. Infra is ready; if valid key is provided later, we can run full autonomous exploit validation on top of this report.
