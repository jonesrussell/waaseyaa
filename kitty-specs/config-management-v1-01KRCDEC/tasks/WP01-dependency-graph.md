---
work_package_id: WP01
title: ConfigDependencyInterface + DAG + cycle/missing detection
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-005
- FR-006
- FR-007
- FR-008
- FR-050
- FR-051
- FR-052
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-config-management-v1-01KRCDEC
base_commit: 89d4e4f83f962d94bdcf29735a683f7a49b704b1
created_at: '2026-05-16T23:35:52.967219+00:00'
subtasks:
- T001
- T002
- T003
- T004
- T005
- T006
- T007
shell_pid: '66861'
history: []
authoritative_surface: packages/config/
execution_mode: code_change
owned_files:
- packages/config/src/Dependency/ConfigDependencyInterface.php
- packages/config/src/Dependency/DependencyGraph.php
- packages/config/src/Dependency/DependencyResolver.php
- packages/config/src/Dependency/Exception/ConfigDependencyCycleException.php
- packages/config/src/Dependency/Exception/ConfigDependencyMissingException.php
- packages/config/tests/Contract/ConfigDependencyInterfaceContractTest.php
- packages/config/tests/Unit/Dependency/DependencyGraphTest.php
- packages/config/tests/Unit/Dependency/DependencyResolverTest.php
- packages/config/tests/Unit/Dependency/Exception/ConfigDependencyCycleExceptionTest.php
- packages/config/tests/Unit/Dependency/Exception/ConfigDependencyMissingExceptionTest.php
---

# Work Package Prompt: WP01 — ConfigDependencyInterface + DAG + cycle/missing detection

## Mission context

- **Mission:** M-003 — Configuration Management v1 — Active/Sync Store Split (`config-management-v1-01KRCDEC`)
- **Spec:** [`../spec.md`](../spec.md) §3 (FRs), §8 (WP table), §5 (sync-store format)
- **Plan:** [`../plan.md`](../plan.md)
- **Governing ADR:** ADR 018 (CMI active/sync split, accepted 2026-05-11)

## Summary

Ship `Waaseyaa\Config\ConfigDependencyInterface` (stable surface), a default no-op implementation hook for `ConfigEntityBase`, the `DependencyGraph` value object, and `DependencyResolver` (DFS topological sort + cycle/missing detection). Cycle and missing-dependency cases raise typed exceptions that carry the offending cycle path or missing id.

## Requirements covered

- FR-001
- FR-002
- FR-003
- FR-004
- FR-005
- FR-006
- FR-007
- FR-008
- FR-050
- FR-051
- FR-052

## Dependencies

No prerequisite WPs — may dispatch immediately on mission start.

## Subtasks

- T001 — Define `ConfigDependencyInterface` with `configDependencies(): array` returning `<entity_type>.<entity_id>` strings (FR-001, FR-002).
- T002 — Add default no-op trait/method to `ConfigEntityBase` returning `[]` so existing entity classes compile unchanged (FR-003).
- T003 — Implement `DependencyGraph` value object (nodes + directed edges + topological order accessor) (FR-004, FR-008).
- T004 — Implement `DependencyResolver` (DFS + tie-break by lexicographic entity-id for deterministic ordering) (FR-004).
- T005 — Implement `ConfigDependencyCycleException` carrying the full cycle path; renderer truncates with `…` after 5 hops in console output (FR-005, FR-050, FR-051).
- T006 — Implement `ConfigDependencyMissingException` carrying the missing id (FR-006, FR-050, FR-051).
- T007 — Contract + unit tests covering no-op default, cycle fixture, missing-dep fixture, `--no-dependency-check` bypass semantics, and stable error-code field (FR-007, FR-052).

## Owned files

- `packages/config/src/Dependency/ConfigDependencyInterface.php`
- `packages/config/src/Dependency/DependencyGraph.php`
- `packages/config/src/Dependency/DependencyResolver.php`
- `packages/config/src/Dependency/Exception/ConfigDependencyCycleException.php`
- `packages/config/src/Dependency/Exception/ConfigDependencyMissingException.php`
- `packages/config/tests/Contract/ConfigDependencyInterfaceContractTest.php`
- `packages/config/tests/Unit/Dependency/DependencyGraphTest.php`
- `packages/config/tests/Unit/Dependency/DependencyResolverTest.php`
- `packages/config/tests/Unit/Dependency/Exception/ConfigDependencyCycleExceptionTest.php`
- `packages/config/tests/Unit/Dependency/Exception/ConfigDependencyMissingExceptionTest.php`

## Acceptance

- All listed FRs covered by tests within this WP's owned files.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green (no upward `waaseyaa/*` edges introduced).
- No modifications outside `owned_files` (other than rerun-of-generators where charter explicitly permits).
