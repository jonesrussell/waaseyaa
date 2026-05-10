---
work_package_id: WP02
title: 'Release entry-point integration: release-cut.yml + scripts/release.sh'
dependencies:
- WP01
requirement_refs:
- FR-002
- FR-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-composer-internal-version-sweep-01KR96NA
base_commit: dcc5e5086cc489a83ef54d664883a16107b62496
created_at: '2026-05-10T15:32:47.415617+00:00'
subtasks:
- T020
- T021
- T022
shell_pid: "263152"
history: []
authoritative_surface: .github/workflows/release-cut.yml
execution_mode: code_change
owned_files:
- .github/workflows/release-cut.yml
- scripts/release.sh
tags: []
agent: "sonnet"
---

# WP02 — Release entry-point integration

Wire `bin/sync-internal-versions` (from WP01) into both release entry points so the per-package literal advances automatically with every cut tag.

## Success criteria

- `release-cut.yml` runs the sync inside its existing `concurrency: release-cut` critical section, before the CHANGELOG-promotion + commit + tag steps.
- `scripts/release.sh` mirrors the same step (deprecated but kept as fallback per #1385).
- Both paths converge: a cut from either ends with the same tree shape (root version field unchanged, per-package literals all `^<version>`, lockfile regenerated).

See `../plan.md` Design Decision D1 (reference is git tag) and Risk R4 (concurrency already handled by the workflow's `concurrency:` group).

## Activity Log

- 2026-05-10T15:32:48Z – sonnet – shell_pid=263152 – Assigned agent via action command
