---
work_package_id: WP11
title: 'Docs closure: spec, cookbook, upgrade-guide, charter §5.5 amendment, CHANGELOG,
  public-surface-map'
dependencies:
- WP04
- WP05
- WP07
- WP09
- WP10
requirement_refs:
- FR-057
- FR-058
- FR-059
- FR-060
- FR-061
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During
  /spec-kitty.implement this WP may branch from a dependency-specific base, but completed
  changes must merge back into main unless the human explicitly redirects the landing
  branch.
base_branch: main
base_commit: 8f2f2c483d1819983bb56e654278d41bc2c76d57
created_at: '2026-05-16T00:00:00+00:00'
subtasks:
- T056
- T057
- T058
- T059
- T060
- T061
- T062
- T063
- T064
shell_pid: ''
history: []
authoritative_surface: docs/
execution_mode: code_change
owned_files:
- docs/specs/config-management.md
- docs/cookbook/config-sync.md
- docs/upgrade-notes/config-management-v1.md
- docs/specs/stability-charter.md
- docs/specs/public-surface-map.md
- docs/public-surface-map.php
- CLAUDE.md
- CHANGELOG.md
---

# Work Package Prompt: WP11 — Docs closure: spec, cookbook, upgrade-guide, charter §5.5 amendment, CHANGELOG, public-surface-map

## Mission context

- **Mission:** M-003 — Configuration Management v1 — Active/Sync Store Split (`config-management-v1-01KRCDEC`)
- **Spec:** [`../spec.md`](../spec.md) §3 (FRs), §8 (WP table), §5 (sync-store format)
- **Plan:** [`../plan.md`](../plan.md)
- **Governing ADR:** ADR 018 (CMI active/sync split, accepted 2026-05-11)

## Summary

Documentation closure: ship canonical `docs/specs/config-management.md`, operator cookbook (`docs/cookbook/config-sync.md`) with per-environment override pattern (env vars in `config/waaseyaa.php`, NOT sync-store overrides), alpha-train upgrade-guide entry, charter §5.5 amendment, public-surface-map entries (MD + PHP), CLAUDE.md orchestration row, CHANGELOG `[Unreleased]` bullets.

## Requirements covered

- FR-057
- FR-058
- FR-059
- FR-060
- FR-061

## Dependencies

This WP depends on: WP04, WP05, WP07, WP09, WP10.

## Subtasks

- T056 — Ship canonical `docs/specs/config-management.md` covering the shipped surface (FR-057).
- T057 — Ship `docs/cookbook/config-sync.md`: setup, conflict handling, rollback recipe, prominent per-environment override pattern (env vars in `config/waaseyaa.php`, NOT sync-store overrides) (FR-058, FR-061).
- T058 — Ship alpha-train upgrade-guide entry per charter §7 (FR-059).
- T059 — Amend charter §5.5 to reference `ConfigDependencyInterface`, the sync-store format, and the six `config:*` commands (FR-060).
- T060 — Add public-surface-map entries (MD + PHP) for the new stable surface with tier=stable, status=present.
- T061 — Add CLAUDE.md orchestration row for `packages/config` sync/audit subsystems.
- T062 — Add CHANGELOG `[Unreleased]` Added bullets.
- T063 — Cross-link the closing artifacts to spec.md §4 deliverables and acceptance criteria §9.
- T064 — Stamp post-mortem note on `docs/specs/config-management.md` linking to the spec-kitty mission slug.

## Owned files

- `docs/specs/config-management.md`
- `docs/cookbook/config-sync.md`
- `docs/upgrade-notes/config-management-v1.md`
- `docs/specs/stability-charter.md`
- `docs/specs/public-surface-map.md`
- `docs/public-surface-map.php`
- `CLAUDE.md`
- `CHANGELOG.md`

## Acceptance

- All listed FRs covered by tests within this WP's owned files.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green (no upward `waaseyaa/*` edges introduced).
- No modifications outside `owned_files` (other than rerun-of-generators where charter explicitly permits).
