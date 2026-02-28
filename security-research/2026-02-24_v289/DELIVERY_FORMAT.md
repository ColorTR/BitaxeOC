# Security Research Delivery Format

This format is mandatory for all 12 workstreams.

## A. Executive Summary

- Scope and date range
- Overall risk posture (P0/P1/P2 counts)
- Change recommendation summary

## B. Per-Workstream Section Template

### 1) Workstream Metadata
- ID: `WS-XX`
- Title:
- Priority:
- Owner:
- Status: `Not Started | In Progress | Blocked | Completed`

### 2) Threat Model / Objective
- What is being protected
- Threat actors
- Attack paths considered

### 3) Test Design
- Test cases (positive/negative/adversarial)
- Tools and commands
- Data sets / fixtures used

### 4) Evidence
- Command + timestamp
- Result snippets
- File references (absolute paths)
- URL references (live checks)

### 5) Findings
- Finding ID
- Severity / likelihood / priority
- Reproduction steps
- Root cause
- Impact radius

### 6) Recommended Actions
- Option A (recommended)
- Option B
- Option C / defer
- Effort, risk, expected impact

### 7) Acceptance Criteria
- Objective pass/fail checks
- Regression checks
- Closure conditions

## C. Findings Severity Scale

- Critical: direct compromise/data exposure path
- High: realistic exploit path with major impact
- Medium: exploitable with constraints / defense gap
- Low: hygiene issue with bounded impact

## D. Report Artifacts

- `EXECUTION_PLAN_12.md`
- `RUN_XX_*.md` (run-by-run evidence logs)
- `FINDINGS_REGISTER.md` (central finding index)
- `PATCH_PLAN.md` (only after approval)

## E. Final Handover Packet

1. Final consolidated report (all workstreams)
2. Finding register with statuses
3. Patch plan with acceptance tests
4. Rollback and verification checklist
