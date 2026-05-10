---
work_package_id: WP01
title: Sync helper script + library + tests
dependencies: []
requirement_refs:
- FR-001
- FR-006
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-composer-internal-version-sweep-01KR96NA
base_commit: 297934b3c3b3cfd9ce4ed32004e467aa73e8d78b
created_at: '2026-05-10T15:10:00+00:00'
subtasks:
- T010
- T011
- T012
- T013
- T014
history: []
authoritative_surface: bin/sync-internal-versions
execution_mode: code_change
owned_files:
- bin/sync-internal-versions
- bin/lib/internal-version-sync.php
- tests/Integration/ReleaseTooling/SyncInternalVersionsTest.php
- tests/Fixtures/release-tooling/manifest-trailing-comma.json
- tests/Fixtures/release-tooling/manifest-unusual-key-order.json
- tests/Fixtures/release-tooling/manifest-with-require-dev.json
tags: []
---

# WP01 — Sync helper script + library + tests

Build the foundation: a PHP script that, given a target version, rewrites every internal `waaseyaa/*` constraint across `packages/*/composer.json` to `^<target>`, idempotently and with JSON formatting preserved. Extract the shared logic into a small library so WP03's CP-NEW gate can reuse it.

## Success criteria

- `bin/sync-internal-versions 0.1.0-alpha.999` rewrites manifests; second run produces no diff.
- Invalid arguments (`""`, `dev-main`, `self.version`, `^*`, `0.1.x`, whitespace) exit non-zero with an actionable message; no files modified.
- JSON formatting preserved: trailing commas, key order, indentation untouched (round-trip via `Composer\Json\JsonFile`).
- New tests pass; PHPStan, cs-check, package-layers, existing composer-policy gates green.

See `../tasks.md` for the T-IDs in this WP. See `../plan.md` for design decisions D2 (JSON round-trip) and D3 (sync vs gate separation).
