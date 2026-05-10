---
work_package_id: WP03
title: 'CP-NEW gate: cross-file consistency check + CI tag access'
dependencies:
- WP01
requirement_refs:
- FR-005
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-composer-internal-version-sweep-01KR96NA
base_commit: dcc5e5086cc489a83ef54d664883a16107b62496
created_at: '2026-05-10T15:39:46.780839+00:00'
subtasks:
- T030
- T031
- T032
- T033
shell_pid: "264766"
history: []
authoritative_surface: bin/check-composer-policy
execution_mode: code_change
owned_files:
- bin/check-composer-policy
- .github/workflows/ci.yml
- tests/Integration/ReleaseTooling/CpNewCheckTest.php
tags: []
agent: "sonnet"
---

# WP03 — CP-NEW gate

Extend `bin/check-composer-policy` with **CP-NEW**: every `waaseyaa/*` constraint in `packages/*/composer.json` must equal `^<resolveCurrentVersion()>`. Reuse `bin/lib/internal-version-sync.php` from WP01.

## Success criteria

- Tampering with one package's literal makes the gate exit non-zero with file path + expected value.
- Matched files (post-WP04 backfill) produce zero exit.
- `.github/workflows/ci.yml`'s `composer-policy` job's `actions/checkout` step has `fetch-tags: true` (or `fetch-depth: 0`) so the gate can resolve the latest tag (Risk R2).
- Verified on a real PR — CI's `composer-policy` job sees the tag and passes.

See `../plan.md` Design Decision D1 (git-tag reference) and Risk R2 (shallow-clone tag fetch).

## Activity Log

- 2026-05-10T15:39:48Z – sonnet – shell_pid=264766 – Assigned agent via action command
- 2026-05-10T15:43:17Z – sonnet – shell_pid=264766 – Ready for review
