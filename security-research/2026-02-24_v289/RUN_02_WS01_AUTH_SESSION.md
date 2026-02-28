# RUN 02 - WS-01 Ops Panel Auth/Session (Initial Deep Dive)

Date (UTC): 2026-02-24  
Workstream: WS-01 (P0)  
Scope:
- `/Users/colortr/Downloads/aaa_fork/bitaxe-oc/ops-panel.php`
- `/Users/colortr/Downloads/aaa_fork/bitaxe-oc/app/Config.php`
- `/Users/colortr/Downloads/aaa_fork/bitaxe-oc/app/Security.php`

## What was validated (static control tracing)

1. Dedicated admin session and CSRF keys are used (`bitaxeoc_admin_*`).
2. Session regeneration is called on:
   - successful login
   - logout
   - remember-cookie re-auth path
3. CSRF + same-origin checks are enforced on POST.
4. Per-identity + global login rate limits are present.
5. Optional access-key gate and IP allowlist gate exist.
6. Remember-cookie:
   - signed via HMAC-SHA256
   - contains username + expiry + version
   - `httponly=true`, `secure` conditional on HTTPS, `samesite=Lax`
7. Admin endpoint has:
   - `Cache-Control: no-store`
   - `X-Robots-Tag: noindex, nofollow, noarchive`
   - anonymous `ajax=server_status` -> `401`

## Evidence points

- Session/csrf config merge:
  - `ops-panel.php` around admin session bootstrap (`session_name`, `csrf_session_key`).
- Session regeneration:
  - `ops-panel.php` login/logout/remember branches (`session_regenerate_id(true)`).
- POST guards:
  - `Security::assertSameOriginRequest()`
  - `Security::assertCsrfToken(...)`
- Login throttling:
  - `Security::applyRateLimitConfig(...)` for local/global scopes.
- Remember cookie implementation:
  - `buildRememberToken`, `parseRememberToken`, `setcookie` options.

## Early risk notes (pending dynamic verification)

1. `rememberSecret` currently derives from config credentials/keys at runtime.  
   - If credential rotation is not coordinated, cookie invalidation behavior must be verified.
2. Cookie policy uses `SameSite=Lax` (intentional for continuity).  
   - Need adversarial validation for cross-site navigation patterns.
3. `bind_session_to_ip` and `bind_session_to_user_agent` are configurable and currently relaxed by design.  
   - Need abuse simulation to validate tradeoff remains acceptable.

## Next step (WS-01 dynamic)

RUN 03 will execute scripted dynamic checks:
- replay of stale remember-cookie
- CSRF failure response checks on login/logout posts
- rate-limit threshold hit confirmation with repeated bad login attempts
- session invalidation behavior after timeout simulation
