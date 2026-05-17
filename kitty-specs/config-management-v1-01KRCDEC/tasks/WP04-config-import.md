---
work_package_id: WP04
title: config:import command + ConfigImporter (DAG order, per-entity tx, orphan-warn)
dependencies:
- WP01
- WP02
requirement_refs:
- FR-022
- FR-023
- FR-024
- FR-025
- FR-026
- FR-027
- FR-028
- FR-029
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
- T021
- T022
- T023
- T024
- T025
- T026
- T027
shell_pid: "82402"
history: []
authoritative_surface: packages/config/
execution_mode: code_change
owned_files:
- packages/config/src/Sync/ConfigImporter.php
- packages/config/src/Exception/ConfigImportFailedException.php
- packages/cli/src/Command/Config/ConfigImportCommand.php
- packages/config/tests/Contract/ConfigImporterContractTest.php
- packages/config/tests/Unit/Sync/ConfigImporterTest.php
- packages/config/tests/Unit/Exception/ConfigImportFailedExceptionTest.php
- packages/cli/tests/Unit/Command/Config/ConfigImportCommandTest.php
agent: "claude:sonnet:python-implementer:implementer"
---

# Work Package Prompt: WP04 — config:import command + ConfigImporter (DAG order, per-entity tx, orphan-warn)

## Mission context

- **Mission:** M-003 — Configuration Management v1 — Active/Sync Store Split (`config-management-v1-01KRCDEC`)
- **Spec:** [`../spec.md`](../spec.md) §3 (FRs), §8 (WP table), §5 (sync-store format)
- **Plan:** [`../plan.md`](../plan.md)
- **Governing ADR:** ADR 018 (CMI active/sync split, accepted 2026-05-11)

## Summary

`config:import` command + `ConfigImporter`: validate every sync-store entry, build the WP01 dependency DAG, apply entities in topological order with per-entity transactions. Orphan handling defaults to **warn** (logged to `config.audit`); `--delete-orphans` opts into deletion. Flags: `--dry-run`, `--halt-on-error`, `--no-dependency-check` (emergency bypass).

## Requirements covered

- FR-022
- FR-023
- FR-024
- FR-025
- FR-026
- FR-027
- FR-028
- FR-029

## Dependencies

This WP depends on: WP01, WP02.

## Subtasks

- T021 — Implement `ConfigImporter` orchestration: validate, build DAG (delegates to WP01), iterate in topological order (FR-022).
- T022 — Implement per-entity transaction boundary; successes commit, failures roll back the individual entity (FR-023).
- T023 — Implement `--dry-run` flag with per-entity preview output (FR-024, FR-025).
- T024 — Implement orphan handling: default warn-to-`config.audit`; `--delete-orphans` opts into deletion (FR-026).
- T025 — Implement per-entity validation hook + `ConfigImportFailedException` with stable error code (FR-027, FR-028).
- T026 — Implement `--halt-on-error` and `--no-dependency-check` flags (FR-028, FR-007 wiring).
- T027 — Register `config:import` command + summary emitter + final exit-code (0 only if all succeeded) (FR-029).

## Owned files

- `packages/config/src/Sync/ConfigImporter.php`
- `packages/config/src/Exception/ConfigImportFailedException.php`
- `packages/cli/src/Command/Config/ConfigImportCommand.php`
- `packages/config/tests/Contract/ConfigImporterContractTest.php`
- `packages/config/tests/Unit/Sync/ConfigImporterTest.php`
- `packages/config/tests/Unit/Exception/ConfigImportFailedExceptionTest.php`
- `packages/cli/tests/Unit/Command/Config/ConfigImportCommandTest.php`

## Acceptance

- All listed FRs covered by tests within this WP's owned files.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green (no upward `waaseyaa/*` edges introduced).
- No modifications outside `owned_files` (other than rerun-of-generators where charter explicitly permits).

## Activity Log

- 2026-05-17T00:25:01Z – claude:sonnet:python-implementer:implementer – shell_pid=82402 – Started implementation via action command
