# RUN 07 - WS-07 Nginx/CSP/WAF + WS-08 Dependency/Supply Chain

Date (UTC): 2026-02-24
Workstreams: WS-07 (P1), WS-08 (P1)

## WS-07 Header/CSP/Edge Hardening

### Evidence

From `https://oc.colortr.com/`:
- HSTS, XFO, XCTO, Referrer-Policy, Permissions-Policy, CORP/COOP, CSP present
- CSP still includes:
  - `script-src 'unsafe-inline' 'unsafe-eval'`
  - external script hosts (tailwind/jsdelivr/cdnjs)

Nginx site config (`/etc/nginx/sites-available/oc-colortr.conf`):
- internal paths blocked (`/app`, `/storage`, `/tmp`, `/bench`, `/demo`, etc.)
- backup dot-path blocked
- static immutable cache enabled
- no explicit `limit_req/limit_conn` policy for oc site block

### WS-07 Findings

- CSP is functional but permissive; hardening opportunity remains.
- Public hardening headers are strong baseline.
- No edge rate-limit gate at nginx layer for oc routes.

## WS-08 Supply Chain / Dependency

### Evidence

Frontend runtime dependencies:
- local vendored:
  - `assets/vendor/chart.umd.min.js` (Chart.js 4.5.1)
  - `assets/vendor/html2canvas.min.js` (1.4.1)
  - `assets/vendor/tailwindcss-cdn.js`
- CDN fallbacks still active in app logic.

Server package posture:
- Active services: nginx, mariadb, fail2ban
- Upgradable packages observed: kernel/meta packages (`linux-generic`, headers/tools)

### WS-08 Findings

- Local vendor fallback improves resilience, but CSP/CDN fallback path keeps external trust chain.
- Regular kernel patch cadence is needed (updates pending).

## Exit Criteria

WS-07:
- no critical missing headers: PASS
- clear plan to reduce permissive directives: PASS

WS-08:
- unresolved critical dependency risk: none observed in this run
- controlled upgrade backlog: PASS (kernel updates pending)
