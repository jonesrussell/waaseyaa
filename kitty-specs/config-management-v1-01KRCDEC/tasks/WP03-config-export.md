---
work_package_id: WP03
title: config:export command + ConfigExporter orchestrator
dependencies:
- WP02
requirement_refs:
- FR-017
- FR-018
- FR-019
- FR-020
- FR-021
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
- T016
- T017
- T018
- T019
- T020
shell_pid: "75093"
history: []
authoritative_surface: packages/config/
execution_mode: code_change
owned_files:
- packages/config/src/Sync/ConfigExporter.php
- packages/cli/src/Command/Config/ConfigExportCommand.php
- packages/config/tests/Unit/Sync/ConfigExporterTest.php
- packages/cli/tests/Unit/Command/Config/ConfigExportCommandTest.php
agent: "claude:sonnet:python-implementer:implementer"
---

# Work Package Prompt: WP03 — config:export command + ConfigExporter orchestrator

## Mission context

- **Mission:** M-003 — Configuration Management v1 — Active/Sync Store Split (`config-management-v1-01KRCDEC`)
- **Spec:** [`../spec.md`](../spec.md) §3 (FRs), §8 (WP table), §5 (sync-store format)
- **Plan:** [`../plan.md`](../plan.md)
- **Governing ADR:** ADR 018 (CMI active/sync split, accepted 2026-05-11)

## Summary

`config:export` command + `ConfigExporter` orchestrator: iterate the config-entity registry, serialize each entity per WP02, write to the sync store. Support `--diff` (write only changed) and `--dry-run` (compute writes without filesystem effects). Summary line: "X created, Y updated, Z unchanged."

## Requirements covered

- FR-017
- FR-018
- FR-019
- FR-020
- FR-021

## Dependencies

This WP depends on: WP02.

## Subtasks

- T016 — Implement `ConfigExporter` (iterate config-entity registry, serialize each entity, dispatch to repository) (FR-017).
- T017 — Implement `--diff` flag (write only changed files; preserves git mtime semantics) (FR-018).
- T018 — Implement `--dry-run` flag (compute would-be writes, no filesystem effects) (FR-019).
- T019 — Implement summary line emitter: "X created, Y updated, Z unchanged." (FR-020).
- T020 — Register `config:export` command in `packages/cli` with exit-code policy (0 success, 1 on serialization error) + unit tests (FR-021).

## Owned files

- `packages/config/src/Sync/ConfigExporter.php`
- `packages/cli/src/Command/Config/ConfigExportCommand.php`
- `packages/config/tests/Unit/Sync/ConfigExporterTest.php`
- `packages/cli/tests/Unit/Command/Config/ConfigExportCommandTest.php`

## Acceptance

- All listed FRs covered by tests within this WP's owned files.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green (no upward `waaseyaa/*` edges introduced).
- No modifications outside `owned_files` (other than rerun-of-generators where charter explicitly permits).

## Activity Log

- 2026-05-17T00:03:41Z – claude:sonnet:python-implementer:implementer – shell_pid=75093 – Started implementation via action command
