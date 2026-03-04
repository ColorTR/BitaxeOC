# AGENTS.md (bitaxe-oc App)

This file defines app-specific invariants for `bitaxe-oc`.

## 1) Core Product Invariants

1. Main app URL is root of production domain (`https://oc.colortr.com/`), not `/bitaxe-oc/`.
2. Upload must not auto-start analysis when files are selected.
3. Primary action label switches from initial start state to recalculation state after first analysis.
4. Sample preview is initial entry helper only; it must not stay selected incorrectly when user manages files.
5. Drag/drop, filtering, chart tooltips, and panel close behaviors must remain functional.

## 2) Share Behavior Invariants

1. `?share=test` uses static test-share behavior.
2. Same dataset/layout should reuse same share URL token behavior where designed.
3. Shared mode must enforce readonly/hardening rules:
  - hide share re-share path on shared view
  - hide controls restricted in shared mode
  - disable panel drag and close interactions in shared mode
4. Unknown/invalid share token must gracefully recover to editable app state:
  - no readonly lock
  - upload UI available
  - no broken overlay state

## 3) Export Invariants

1. HTML export must remain interactive where intended (not dead image-only chart fallback unless explicitly requested).
2. Export must not mutate live app state.
3. Test-generated export files can be cleaned by tests, but user-generated exports must not be deleted by app logic.

## 4) Theme & Visual Stability

1. Default startup remains dark mode unless user preference exists.
2. User-selected theme/variant persists and restores correctly.
3. Light/dark adaptive transition effects must keep layout stable.
4. No unrequested typography/icon/layout redesign in production.

## 5) Limits (Operational Defaults)

Keep these unless user explicitly changes:
- `max_files_per_batch = 30`
- `max_file_bytes = 350 * 1024`
- `max_total_bytes = 6 * 1024 * 1024`
- `csv_max_data_rows = 7000`

Limit changes require:
1. explicit justification
2. bench validation
3. master test pass

## 6) Ops Panel Invariants

1. Single panel source file: `ops-panel.php`.
2. Do not reintroduce split `ops-panel2.php` architecture unless explicitly requested.
3. `ops-panel.php?ajax=server_status` must:
  - require auth
  - return `401` when anonymous
  - keep rate limiting
  - keep no-store/noindex protections
4. Keep remember-auth/session hardening behavior intact.

## 7) Security Rules

1. Keep CSRF + same-origin checks on admin POST actions.
2. Keep login rate limits (per identity + global).
3. Keep optional access-key + IP allowlist gates functional.
4. Keep fallback-safe behavior for DB/log access errors (no fatal page break).
5. Never commit plaintext production secrets.

## 8) Done Criteria for App Changes

A change is done only if:
1. changed flows are manually/automatically verified
2. no regression in share/upload/export/theme/ops panel critical paths
3. relevant tests pass on live target

## 9) App Release Checklist

1. Update app version in `app/Config.php` and append one-line note in `VERSION_LOG.md`.
2. Confirm critical invariants in this file are untouched (share/upload/export/theme/ops panel).
3. Run mandatory app tests:
  - backend smoke/unit
  - live HTTP audit
  - ops panel audit
  - master test when requested
4. Deploy to VPS and verify:
  - app root
  - share test and invalid-share fallback
  - ops panel auth and server status protections
5. If user requested master, trigger master backup flow after green tests.

## 9.1) GitHub Backup Workflow (Mandatory)

1. Repo scope is only this project: `ColorTR/BitaxeOC` (private).
2. For every code/config change in this project:
  - commit locally
  - push to `main`
3. When user marks a version as `master`:
  - create/update version tag (`vNNN`)
  - publish private GitHub Release for that tag
  - attach master backup zip asset (`bitaxe-oc_master_backend_vNNN.zip`)
4. GitHub release title/body language policy:
  - always write release notes in English
  - keep wording concise, technical, and production-focused
5. Do not touch other repositories/projects unless explicitly requested.

## 10) App Incident Rollback Checklist

1. Lock release activity and identify last known good version.
2. Preserve current DB/code snapshot metadata before rollback.
3. Restore last good master code package from VPS backup root.
4. Restart app process and validate:
  - main app page
  - share flow
  - ops panel critical paths
5. Re-run smoke + HTTP + ops audits before marking incident mitigated.

<!-- gitnexus:start -->
# GitNexus MCP

This project is indexed by GitNexus as **bitaxe-oc** (688 symbols, 1825 relationships, 54 execution flows).

## Always Start Here

1. **Read `gitnexus://repo/{name}/context`** — codebase overview + check index freshness
2. **Match your task to a skill below** and **read that skill file**
3. **Follow the skill's workflow and checklist**

> If step 1 warns the index is stale, run `npx gitnexus analyze` in the terminal first.

## Skills

| Task | Read this skill file |
|------|---------------------|
| Understand architecture / "How does X work?" | `.claude/skills/gitnexus/gitnexus-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/gitnexus/gitnexus-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/gitnexus/gitnexus-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/gitnexus/gitnexus-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/gitnexus/gitnexus-cli/SKILL.md` |

<!-- gitnexus:end -->
