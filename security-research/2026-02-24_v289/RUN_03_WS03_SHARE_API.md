# RUN 03 - WS-03 Share API Abuse + Token Model

Date (UTC): 2026-02-24
Workstream: WS-03 (P0)
Target: https://oc.colortr.com/api/share.php

## Test Design

- Same payload create twice -> dedupe token reuse
- Wrong origin request
- Replay nonce (same timestamp+nonce twice)
- ETag/If-None-Match 304 path
- Invalid/unknown token read path
- Out-of-window timestamp path (server-side confirmation)

## Evidence (Local -> Public URL)

Command family (curl+jq): POST/GET matrix against `https://oc.colortr.com/api/share.php`

Observed:
- `create #1`: HTTP `201`, token `d844da6196dcc3ec06954d88`
- `create #2` (same payload): HTTP `200`, same token, `reused=true`
- wrong origin: HTTP `403`, `Origin dogrulamasi basarisiz.`
- replay second request: HTTP `409`, `Tekrarlanan istek engellendi.`
- GET with ETag then If-None-Match: first `200`, second `304`
- invalid token: HTTP `404`, `Paylasim linki bulunamadi veya suresi doldu.`

Local client note:
- Out-of-window timestamp requests returned curl `Empty reply from server` from workstation path.

## Evidence (Server-side Ground Truth)

Remote check (SSH -> localhost:3001 with Host header):

- `server_create1=201 token1=d6441f9cb39e2a333976b3df`
- `server_create2=200 token2=d6441f9cb39e2a333976b3df reused2=true same_token=yes`
- `server_stale=408 body={"ok":false,"error":"Istek zamani asimina ugradi."}`
- `server_wrong_origin=403 body={"ok":false,"error":"Origin dogrulamasi basarisiz."}`

Nginx + app logs confirm stale requests are handled as HTTP 408 on server.

## Findings

- No unauthorized share read/write path reproduced.
- Dedupe/replay/origin/etag model behaves as intended in server runtime.
- Workstation-side `Empty reply` on 408 is environmental path noise (client/proxy path), not server logic failure.

## WS-03 Exit Criteria

- no unauthorized data exposure: PASS
- replay/origin checks enforced: PASS
- dedupe isolation: PASS
