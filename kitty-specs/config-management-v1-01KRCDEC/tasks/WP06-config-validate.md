---
work_package_id: WP06
title: config:validate + ConfigSyncValidator (reuses FieldDefinition::validators())
dependencies:
- WP02
requirement_refs:
- FR-037
- FR-038
- FR-039
- FR-040
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
- T034
- T035
- T036
- T037
shell_pid: "91738"
history: []
authoritative_surface: packages/config/
execution_mode: code_change
owned_files:
- packages/config/src/Sync/ConfigSyncValidator.php
- packages/cli/src/Command/Config/ConfigValidateCommand.php
- packages/config/tests/Unit/Sync/ConfigSyncValidatorTest.php
- packages/cli/tests/Unit/Command/Config/ConfigValidateCommandTest.php
agent: "claude:opus:python-reviewer:reviewer"
---

# Work Package Prompt: WP06 — config:validate + ConfigSyncValidator (reuses FieldDefinition::validators())

## Mission context

- **Mission:** M-003 — Configuration Management v1 — Active/Sync Store Split (`config-management-v1-01KRCDEC`)
- **Spec:** [`../spec.md`](../spec.md) §3 (FRs), §8 (WP table), §5 (sync-store format)
- **Plan:** [`../plan.md`](../plan.md)
- **Governing ADR:** ADR 018 (CMI active/sync split, accepted 2026-05-11)

## Summary

`config:validate` command + `ConfigSyncValidator`: parse every sync-store file, instantiate would-be entities without persisting, run `FieldDefinition::validators()` per ADR 013 over each field. Per-entity error detail; CI-runnable as a deploy-time gate.

## Requirements covered

- FR-037
- FR-038
- FR-039
- FR-040

## Dependencies

This WP depends on: WP02.

## Subtasks

- T034 — Implement `ConfigSyncValidator`: parse sync files, instantiate would-be entity, run `FieldDefinition::validators()` per ADR 013 (FR-037).
- T035 — Output per-entity errors with per-field detail (FR-039).
- T036 — Wire validation as `config:import` precondition; bypassed only with `--no-dependency-check` (FR-038).
- T037 — Register `config:validate` command (CI-runnable, exit 0/1) + tests covering CI gate semantics (FR-040).

## Owned files

- `packages/config/src/Sync/ConfigSyncValidator.php`
- `packages/cli/src/Command/Config/ConfigValidateCommand.php`
- `packages/config/tests/Unit/Sync/ConfigSyncValidatorTest.php`
- `packages/cli/tests/Unit/Command/Config/ConfigValidateCommandTest.php`

## Acceptance

- All listed FRs covered by tests within this WP's owned files.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green (no upward `waaseyaa/*` edges introduced).
- No modifications outside `owned_files` (other than rerun-of-generators where charter explicitly permits).

## Activity Log

- 2026-05-17T00:48:31Z – claude:sonnet:python-implementer:implementer – shell_pid=89608 – Started implementation via action command
- 2026-05-17T00:54:40Z – claude:sonnet:python-implementer:implementer – shell_pid=89608 – WP06 ready: config:validate + ConfigValidator
- 2026-05-17T00:55:09Z – claude:opus:python-reviewer:reviewer – shell_pid=91738 – Started review via action command
