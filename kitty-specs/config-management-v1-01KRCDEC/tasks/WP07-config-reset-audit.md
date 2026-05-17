---
work_package_id: WP07
title: config:reset + ConfigResetter + config.audit log channel
dependencies:
- WP04
requirement_refs:
- FR-041
- FR-042
- FR-043
- FR-053
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
- T038
- T039
- T040
- T041
- T042
shell_pid: "94896"
history: []
authoritative_surface: packages/config/
execution_mode: code_change
owned_files:
- packages/config/src/Sync/ConfigResetter.php
- packages/config/src/Audit/ConfigAuditChannel.php
- packages/config/src/Audit/ConfigAuditEvent.php
- packages/cli/src/Command/Config/ConfigResetCommand.php
- packages/config/tests/Unit/Sync/ConfigResetterTest.php
- packages/config/tests/Unit/Audit/ConfigAuditChannelTest.php
- packages/config/tests/Unit/Audit/ConfigAuditEventTest.php
- packages/cli/tests/Unit/Command/Config/ConfigResetCommandTest.php
agent: "claude:opus:python-reviewer:reviewer"
---

# Work Package Prompt: WP07 — config:reset + ConfigResetter + config.audit log channel

## Mission context

- **Mission:** M-003 — Configuration Management v1 — Active/Sync Store Split (`config-management-v1-01KRCDEC`)
- **Spec:** [`../spec.md`](../spec.md) §3 (FRs), §8 (WP table), §5 (sync-store format)
- **Plan:** [`../plan.md`](../plan.md)
- **Governing ADR:** ADR 018 (CMI active/sync split, accepted 2026-05-11)

## Summary

`config:reset <entity-type>.<id>` command + `ConfigResetter` + `config.audit` log channel (stable surface). Prompt-for-confirm unless `--yes`. Audit channel records operation, actor, before/after summary, timestamp.

## Requirements covered

- FR-041
- FR-042
- FR-043
- FR-053

## Dependencies

This WP depends on: WP04.

## Subtasks

- T038 — Implement `ConfigResetter` (single-entity reset, transactional, lifecycle events fire) (FR-041).
- T039 — Implement confirmation prompt (suppressed under `--yes`; refuses non-TTY without `--yes` per plan complexity tracking) (FR-042).
- T040 — Implement `ConfigAuditChannel` constant + register on stable surface per charter §4.4 amendment (FR-053).
- T041 — Implement `ConfigAuditEvent` value object (operation, actor, entity, before/after summary, timestamp) (FR-043).
- T042 — Register `config:reset <entity-type>.<id>` command + audit logging + tests (FR-041, FR-043).

## Owned files

- `packages/config/src/Sync/ConfigResetter.php`
- `packages/config/src/Audit/ConfigAuditChannel.php`
- `packages/config/src/Audit/ConfigAuditEvent.php`
- `packages/cli/src/Command/Config/ConfigResetCommand.php`
- `packages/config/tests/Unit/Sync/ConfigResetterTest.php`
- `packages/config/tests/Unit/Audit/ConfigAuditChannelTest.php`
- `packages/config/tests/Unit/Audit/ConfigAuditEventTest.php`
- `packages/cli/tests/Unit/Command/Config/ConfigResetCommandTest.php`

## Acceptance

- All listed FRs covered by tests within this WP's owned files.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green (no upward `waaseyaa/*` edges introduced).
- No modifications outside `owned_files` (other than rerun-of-generators where charter explicitly permits).

## Activity Log

- 2026-05-17T00:57:08Z – claude:sonnet:python-implementer:implementer – shell_pid=92656 – Started implementation via action command
- 2026-05-17T01:04:38Z – claude:sonnet:python-implementer:implementer – shell_pid=92656 – WP07 ready: config:reset + audit channel
- 2026-05-17T01:05:08Z – claude:opus:python-reviewer:reviewer – shell_pid=94896 – Started review via action command
