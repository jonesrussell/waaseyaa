---
work_package_id: WP05
title: config:diff + config:status + shared diff renderer + UUID rename detection
dependencies:
- WP02
requirement_refs:
- FR-030
- FR-031
- FR-032
- FR-033
- FR-034
- FR-035
- FR-036
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
- T028
- T029
- T030
- T031
- T032
- T033
shell_pid: "88580"
history: []
authoritative_surface: packages/config/
execution_mode: code_change
owned_files:
- packages/config/src/Sync/ConfigDiffer.php
- packages/config/src/Sync/ConfigStatusReporter.php
- packages/cli/src/Command/Config/ConfigDiffCommand.php
- packages/cli/src/Command/Config/ConfigStatusCommand.php
- packages/config/tests/Unit/Sync/ConfigDifferTest.php
- packages/config/tests/Unit/Sync/ConfigStatusReporterTest.php
- packages/cli/tests/Unit/Command/Config/ConfigDiffCommandTest.php
- packages/cli/tests/Unit/Command/Config/ConfigStatusCommandTest.php
agent: "claude:opus:python-reviewer:reviewer"
---

# Work Package Prompt: WP05 — config:diff + config:status + shared diff renderer + UUID rename detection

## Mission context

- **Mission:** M-003 — Configuration Management v1 — Active/Sync Store Split (`config-management-v1-01KRCDEC`)
- **Spec:** [`../spec.md`](../spec.md) §3 (FRs), §8 (WP table), §5 (sync-store format)
- **Plan:** [`../plan.md`](../plan.md)
- **Governing ADR:** ADR 018 (CMI active/sync split, accepted 2026-05-11)

## Summary

`config:diff` and `config:status` commands plus the shared unified-diff renderer (`ConfigDiffer`) and `ConfigStatusReporter`. Both sides serialize identically before diffing (no spurious whitespace differences). UUID-tracked rename detection per FR-033. `--format=json` for CI consumption. Read-only.

## Requirements covered

- FR-030
- FR-031
- FR-032
- FR-033
- FR-034
- FR-035
- FR-036

## Dependencies

This WP depends on: WP02.

## Subtasks

- T028 — Implement `ConfigDiffer`: unified diff over identically-serialized YAML; deterministic to avoid spurious whitespace differences (FR-030, FR-031).
- T029 — Implement UUID-tracked rename detection: same `_meta.uuid` + different id renders as rename (FR-033).
- T030 — Implement `ConfigStatusReporter`: in-sync / drift / sync-only / active-only counts; per-entity table when total < 50; per-type set diff (FR-034).
- T031 — Implement `--format=json` flag on `config:status` for CI consumption (FR-035).
- T032 — Register `config:diff` command (whole-store or scoped to `<entity-type>.<id>`; exit 0/1) + tests (FR-030, FR-032).
- T033 — Register `config:status` command (read-only; no side effects) + tests (FR-036).

## Owned files

- `packages/config/src/Sync/ConfigDiffer.php`
- `packages/config/src/Sync/ConfigStatusReporter.php`
- `packages/cli/src/Command/Config/ConfigDiffCommand.php`
- `packages/cli/src/Command/Config/ConfigStatusCommand.php`
- `packages/config/tests/Unit/Sync/ConfigDifferTest.php`
- `packages/config/tests/Unit/Sync/ConfigStatusReporterTest.php`
- `packages/cli/tests/Unit/Command/Config/ConfigDiffCommandTest.php`
- `packages/cli/tests/Unit/Command/Config/ConfigStatusCommandTest.php`

## Acceptance

- All listed FRs covered by tests within this WP's owned files.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green (no upward `waaseyaa/*` edges introduced).
- No modifications outside `owned_files` (other than rerun-of-generators where charter explicitly permits).

## Activity Log

- 2026-05-17T00:37:33Z – claude:sonnet:python-implementer:implementer – shell_pid=86583 – Started implementation via action command
- 2026-05-17T00:45:58Z – claude:sonnet:python-implementer:implementer – shell_pid=86583 – WP05 ready: ConfigDiffer + ConfigStatusReporter + config:diff/config:status CLIs (T028-T033, FR-030..FR-036). 356 config + 578 cli tests green; phpstan/cs/composer-policy/package-layers clean. Commit a501c737a.
- 2026-05-17T00:46:30Z – claude:opus:python-reviewer:reviewer – shell_pid=88580 – Started review via action command
