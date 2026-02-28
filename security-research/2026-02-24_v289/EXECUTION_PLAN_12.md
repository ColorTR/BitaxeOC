# Security Research Execution Plan (12 Items)

Date: 2026-02-24  
Target: https://oc.colortr.com/  
Scope: `/Users/colortr/Downloads/aaa_fork/bitaxe-oc`

Skills used for plan design:
- `security-review` (threat and control checklist)
- `search-first` (tool-first research workflow)

## 0. Research Rules

1. No production behavior change without explicit patch review.
2. Each finding must include reproducible evidence (command/output/path).
3. Risk scoring model:
  - Severity: Critical / High / Medium / Low
  - Likelihood: High / Medium / Low
  - Priority: P0 / P1 / P2
4. Every item closes only with acceptance criteria.

## 1. Workstreams (12 Items)

### WS-01 (P0): Ops Panel Auth Session Threat Model
- Goal: Validate auth/session/remember-cookie attack resistance.
- Scope: `ops-panel.php`, `app/Security.php`.
- Methods:
  - session fixation/regeneration checks
  - cookie flags and expiry checks
  - brute-force/rate-limit behavior under stress
- Evidence:
  - auth flow traces
  - csrf/session lifecycle map
  - reproducible bypass attempts
- Exit criteria:
  - no auth bypass path
  - rate limits trigger as designed
  - remember-cookie logic cannot silently elevate session

### WS-02 (P0): CSV Upload + Parser Fuzzing
- Goal: Break parser safely; detect logic errors and resource abuse.
- Scope: `api/analyze.php`, `app/Analyzer.php`.
- Methods:
  - malformed CSV corpus (delimiter chaos, BOM, huge fields, invalid UTF-8)
  - numeric edge cases (locale separators, NaN-like tokens, exponent abuse)
  - row-limit and size-limit abuse attempts
- Evidence:
  - fuzz corpus inventory
  - crash/wrong-output matrix
  - regression cases promoted to fixtures
- Exit criteria:
  - deterministic output for valid inputs
  - graceful reject for invalid inputs
  - limits enforced without memory spikes

### WS-03 (P0): Share API Abuse + Token Model
- Goal: Validate share token lifecycle and replay resilience.
- Scope: `api/share.php`, `app/ShareStore.php`.
- Methods:
  - token enumeration simulation
  - replay, stale timestamp, wrong-origin permutations
  - ETag/304 cache behavior abuse checks
- Evidence:
  - request matrix (expected vs actual)
  - token entropy and reuse behavior analysis
- Exit criteria:
  - no unauthorized data exposure
  - replay/origin checks consistently enforced
  - dedupe doesn’t cross-contaminate payloads

### WS-04 (P0): Secrets + Config Drift Audit
- Goal: Ensure deploy/runtime secret sources are coherent.
- Scope: `app/Config.php`, `app/Config.secret.php`, VPS runtime env.
- Methods:
  - precedence map (`Config` vs `Config.secret` vs env)
  - zero-secret-in-source verification
  - drift detection between local and VPS
- Evidence:
  - config precedence diagram
  - secret hygiene checklist
  - drift report
- Exit criteria:
  - no plaintext production secrets in repo
  - single authoritative runtime secret path
  - backup scripts compatible with secret strategy

### WS-05 (P1): DB Security + Privilege Review
- Goal: Validate DB least privilege and schema resilience.
- Scope: MySQL `oc_masterdata`, app DB users/tables.
- Methods:
  - grant audit
  - index and query-path review
  - retention/prune stress checks
- Evidence:
  - grants snapshot
  - high-cost query list
  - privilege hardening recommendations
- Exit criteria:
  - least privilege enforced
  - no risky broad grants
  - critical paths indexed

### WS-06 (P1): Backup/Restore Disaster Drill
- Goal: Prove recovery path, not just backup existence.
- Scope: `/opt/oc/.oc_master_backups`.
- Methods:
  - controlled restore dry-run
  - checksum validation
  - restore timing (RTO/RPO)
- Evidence:
  - drill log
  - restore checklist with measured times
- Exit criteria:
  - reproducible restore
  - verified integrity
  - documented RTO/RPO

### WS-07 (P1): Nginx/CSP/WAF Hardening Review
- Goal: Reduce attack surface while preserving UI behavior.
- Scope: headers/CSP/WAF behaviors in production.
- Methods:
  - CSP policy minimization study
  - false-positive-safe WAF rule checks
  - critical endpoint header consistency
- Evidence:
  - header diff report
  - CSP tightening candidate list
- Exit criteria:
  - no critical missing headers
  - clear plan to reduce permissive directives

### WS-08 (P1): Dependency + Supply Chain Audit
- Goal: Detect vulnerable/unsafe dependency paths.
- Scope: vendor JS, test tools, server package chain.
- Methods:
  - static CVE lookup
  - integrity/pinning checks
  - update risk map
- Evidence:
  - dependency inventory
  - CVE triage table
- Exit criteria:
  - no unresolved critical dependency risk
  - controlled upgrade backlog

### WS-09 (P1): Rate Limit + Anti-Automation Resilience
- Goal: Ensure brute/burst paths are throttled as intended.
- Scope: upload/share/ops login flows.
- Methods:
  - burst and distributed-pattern simulations
  - false-lockout UX checks
- Evidence:
  - threshold behavior chart
  - lockout/recovery correctness matrix
- Exit criteria:
  - protective throttling works
  - legitimate usage not blocked excessively

### WS-10 (P2): Security Monitoring + Alert Baseline
- Goal: Make incidents observable and actionable.
- Scope: app logs, ops panel signals, server logs.
- Methods:
  - event taxonomy definition
  - alert threshold design
- Evidence:
  - monitoring blueprint
  - alert runbook draft
- Exit criteria:
  - minimum alert set for P0 scenarios
  - response flow documented

### WS-11 (P2): PenTest Automation Pipeline
- Goal: Convert manual checks to repeatable pipeline.
- Scope: static + dynamic scans + smoke checks.
- Methods:
  - semgrep/gitleaks/zap/trivy integration design
  - pass/fail gating proposal
- Evidence:
  - pipeline spec
  - sample report contract
- Exit criteria:
  - reproducible periodic security job definition

### WS-12 (P2): AI-Agent Config Security Scan
- Goal: Detect prompt-injection and over-permissive agent configs.
- Scope: AGENTS/skills/automation-facing instructions in workspace.
- Methods:
  - rule scan for risky directives
  - privilege-exposure review
- Evidence:
  - config risk table
  - mitigation suggestions
- Exit criteria:
  - no critical prompt-execution risk
  - secure-default authoring guidelines

## 2. Execution Sequence

Phase A (Immediate, P0): WS-01/02/03/04  
Phase B (Next, P1): WS-05/06/07/08/09  
Phase C (Hardening/Automation): WS-10/11/12

## 3. Status (Updated)

- WS-01: Completed (`RUN_02_WS01_AUTH_SESSION.md`)
- WS-02: Completed (`RUN_04_WS02_CSV_FUZZ.md`)
- WS-03: Completed (`RUN_03_WS03_SHARE_API.md`)
- WS-04: Completed with findings (`RUN_05_WS04_WS05_CONFIG_DB.md`)
- WS-05: Completed with findings (`RUN_05_WS04_WS05_CONFIG_DB.md`)
- WS-06: Completed with findings (`RUN_06_WS06_BACKUP_RESTORE.md`)
- WS-07: Completed with findings (`RUN_07_WS07_WS08_INFRA_SUPPLY.md`)
- WS-08: Completed with findings (`RUN_07_WS07_WS08_INFRA_SUPPLY.md`)
- WS-09: Completed (`RUN_08_WS09_RATE_LIMIT.md`)
- WS-10: Completed baseline (`RUN_09_WS10_WS11_WS12.md`)
- WS-11: Completed baseline with gap identified (`RUN_09_WS10_WS11_WS12.md`)
- WS-12: Completed (`RUN_09_WS10_WS11_WS12.md`)
