---
work_package_id: WP05
title: 'Docs + CHANGELOG: CLAUDE.md note, [Unreleased] bullet, drift stamps'
dependencies:
- WP01
- WP04
requirement_refs:
- FR-007
- FR-008
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-composer-internal-version-sweep-01KR96NA
base_commit: 297934b3c3b3cfd9ce4ed32004e467aa73e8d78b
created_at: '2026-05-10T15:10:00+00:00'
subtasks:
- T050
- T051
- T052
- T053
history: []
authoritative_surface: CHANGELOG.md
execution_mode: code_change
owned_files:
- CHANGELOG.md
- CLAUDE.md
tags: []
agent: "sonnet"
shell_pid: "268372"
---

# WP05 — Docs + CHANGELOG + close-out

One-line note in `CLAUDE.md` pointing at the new sync script and CP-NEW. One bullet in `CHANGELOG.md` `[Unreleased]` describing the mechanism. Drift-detector stamps for any specs that map to `bin/check-composer-policy` or `scripts/release.sh`. Mark PR ready.

## Success criteria

- `CLAUDE.md`'s "Composer policy is codified" bullet has a short addition pointing at `bin/sync-internal-versions` and CP-NEW.
- `CHANGELOG.md` `[Unreleased]` `### Changed` has a new bullet referencing this mission slug.
- `tools/drift-detector.sh` reports no STALE specs (or stamps applied where it does).
- All hard gates green on the final commit.
- PR marked ready for review.

## Activity Log

- 2026-05-10T15:51:42Z – sonnet – shell_pid=268372 – Started implementation via action command
