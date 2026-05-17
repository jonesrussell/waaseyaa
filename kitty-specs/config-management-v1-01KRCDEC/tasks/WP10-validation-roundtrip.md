---
work_package_id: WP10
title: 'Validation: Minoo round-trip integration test + cycle fixture + dependency-ordering
  test'
dependencies:
- WP03
- WP04
- WP05
- WP06
requirement_refs:
- FR-054
- FR-055
- FR-056
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
- T051
- T052
- T053
- T054
- T055
shell_pid: "102803"
history: []
authoritative_surface: packages/config/
execution_mode: code_change
owned_files:
- packages/config/tests/Fixtures/CycleFixture.php
- packages/config/tests/Fixtures/MinooRoundTripFixture.php
- tests/Integration/Phase28/ConfigSyncRoundTripIntegrationTest.php
- tests/Integration/Phase28/ConfigImportDependencyOrderingTest.php
- tests/Integration/Phase28/ConfigSyncCycleDetectionTest.php
agent: "claude:sonnet:python-implementer:implementer"
---

# Work Package Prompt: WP10 — Validation: Minoo round-trip integration test + cycle fixture + dependency-ordering test

## Mission context

- **Mission:** M-003 — Configuration Management v1 — Active/Sync Store Split (`config-management-v1-01KRCDEC`)
- **Spec:** [`../spec.md`](../spec.md) §3 (FRs), §8 (WP table), §5 (sync-store format)
- **Plan:** [`../plan.md`](../plan.md)
- **Governing ADR:** ADR 018 (CMI active/sync split, accepted 2026-05-11)

## Summary

Mission validation: Minoo round-trip integration test (export → modify in sync store → import → diff = 0), cycle-detection fixture (A → B → A raises `ConfigDependencyCycleException` with full path), and DAG ordering test exercising real entity types.

## Requirements covered

- FR-054
- FR-055
- FR-056

## Dependencies

This WP depends on: WP03, WP04, WP05, WP06.

## Subtasks

- T051 — Build `CycleFixture` (deliberate `A → B → A` between two taxonomy or role entities) (FR-056).
- T052 — Build `MinooRoundTripFixture` exercising real Minoo config entities (FR-054).
- T053 — Integration test: export → modify sync file → import → diff returns 0 (FR-054).
- T054 — Integration test: round-trip preservation (export → import unchanged → no observable active-store change, no spurious diffs) (FR-055).
- T055 — Integration test: cycle fixture raises `ConfigDependencyCycleException` with full cycle path (FR-056).

## Owned files

- `packages/config/tests/Fixtures/CycleFixture.php`
- `packages/config/tests/Fixtures/MinooRoundTripFixture.php`
- `tests/Integration/Phase28/ConfigSyncRoundTripIntegrationTest.php`
- `tests/Integration/Phase28/ConfigImportDependencyOrderingTest.php`
- `tests/Integration/Phase28/ConfigSyncCycleDetectionTest.php`

## Acceptance

- All listed FRs covered by tests within this WP's owned files.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green (no upward `waaseyaa/*` edges introduced).
- No modifications outside `owned_files` (other than rerun-of-generators where charter explicitly permits).

## Activity Log

- 2026-05-17T01:35:18Z – claude:sonnet:python-implementer:implementer – shell_pid=102803 – Started implementation via action command
