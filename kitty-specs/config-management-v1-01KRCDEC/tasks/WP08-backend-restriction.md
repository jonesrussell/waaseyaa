---
work_package_id: WP08
title: Backend restriction enforcement (sql-blob/sql-column only; boot-time exception)
dependencies: []
requirement_refs:
- FR-044
- FR-045
- FR-046
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-config-management-v1-01KRCDEC
base_commit: 89d4e4f83f962d94bdcf29735a683f7a49b704b1
created_at: '2026-05-17T01:06:58.166547+00:00'
subtasks:
- T043
- T044
- T045
- T046
shell_pid: "95813"
history: []
authoritative_surface: packages/config/
execution_mode: code_change
owned_files:
- packages/config/src/Backend/BackendRestrictionEnforcer.php
- packages/config/src/Exception/InvalidConfigBackendException.php
- packages/entity-storage/src/StorageBackendRegistry.php
- packages/config/tests/Unit/Backend/BackendRestrictionEnforcerTest.php
- packages/config/tests/Unit/Exception/InvalidConfigBackendExceptionTest.php
- packages/entity-storage/tests/Unit/StorageBackendRegistryConfigRestrictionTest.php
agent: "claude:sonnet:python-implementer:implementer"
---

# Work Package Prompt: WP08 — Backend restriction enforcement (sql-blob/sql-column only; boot-time exception)

## Mission context

- **Mission:** M-003 — Configuration Management v1 — Active/Sync Store Split (`config-management-v1-01KRCDEC`)
- **Spec:** [`../spec.md`](../spec.md) §3 (FRs), §8 (WP table), §5 (sync-store format)
- **Plan:** [`../plan.md`](../plan.md)
- **Governing ADR:** ADR 018 (CMI active/sync split, accepted 2026-05-11)

## Summary

Boot-time backend-restriction enforcement: config entities may only live on `sql-blob` / `sql-column`. `BackendRestrictionEnforcer` runs during `StorageBackendRegistry` registration; offending entity types raise `InvalidConfigBackendException` carrying entity-type id, disallowed backend id, and declaring FQCN.

## Requirements covered

- FR-044
- FR-045
- FR-046

## Dependencies

No prerequisite WPs — may dispatch immediately on mission start.

## Subtasks

- T043 — Implement `BackendRestrictionEnforcer`: registry-time check that config entity types declare `sql-blob` or `sql-column` only (FR-044).
- T044 — Implement `InvalidConfigBackendException` carrying entity-type id, disallowed backend id, declaring FQCN (FR-045, FR-046).
- T045 — Wire enforcer into `StorageBackendRegistry` so the check fires at boot before request handling (FR-045).
- T046 — Unit tests covering allow (sql-blob, sql-column) and deny (vector, remote, future-unknown) cases; cookbook runbook hook for recovery.

## Owned files

- `packages/config/src/Backend/BackendRestrictionEnforcer.php`
- `packages/config/src/Exception/InvalidConfigBackendException.php`
- `packages/entity-storage/src/StorageBackendRegistry.php`
- `packages/config/tests/Unit/Backend/BackendRestrictionEnforcerTest.php`
- `packages/config/tests/Unit/Exception/InvalidConfigBackendExceptionTest.php`
- `packages/entity-storage/tests/Unit/StorageBackendRegistryConfigRestrictionTest.php`

## Acceptance

- All listed FRs covered by tests within this WP's owned files.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green (no upward `waaseyaa/*` edges introduced).
- No modifications outside `owned_files` (other than rerun-of-generators where charter explicitly permits).

## Activity Log

- 2026-05-17T01:06:59Z – claude:sonnet:python-implementer:implementer – shell_pid=95813 – Assigned agent via action command
- 2026-05-17T01:25:13Z – claude:sonnet:python-implementer:implementer – shell_pid=95813 – WP08 ready: backend restriction (recovered from stalled agent)
