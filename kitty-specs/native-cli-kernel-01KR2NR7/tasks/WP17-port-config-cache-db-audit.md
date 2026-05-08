---
work_package_id: WP17
title: 'Port: Config + Cache + Db + Audit'
dependencies:
- WP05
requirement_refs:
- FR-010
- FR-012
- FR-015
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T076
- T077
- T078
- T079
- T080
agent: "claude:sonnet:implementer:implementer"
shell_pid: "977665"
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/Command/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/Command/ConfigExport*.php
- packages/cli/src/Command/ConfigImport*.php
- packages/cli/src/Command/CacheClear*.php
- packages/cli/src/Command/DbInit*.php
- packages/cli/src/Command/AuditLog*.php
- packages/cli/src/Provider/ConfigCacheDbAuditServiceProvider.php
- packages/cli/tests/Unit/Command/ConfigExport*Test.php
- packages/cli/tests/Unit/Command/ConfigImport*Test.php
- packages/cli/tests/Unit/Command/CacheClear*Test.php
- packages/cli/tests/Unit/Command/DbInit*Test.php
- packages/cli/tests/Unit/Command/AuditLog*Test.php
- packages/cli/tests/Integration/Snapshot/{ConfigExport,ConfigImport,CacheClear,DbInit,AuditLog}SnapshotTest.php
tags: []
---

# WP17 — Port: Config + Cache + Db + Audit

## Branch Strategy

`main` → `main` per lanes.json.

## Subtasks

### T076 — Port `ConfigExportCommand` → `ConfigExportHandler`
### T077 — Port `ConfigImportCommand` → `ConfigImportHandler`
### T078 — Port `CacheClearCommand` → `CacheClearHandler`
### T079 — Port `DbInitCommand` → `DbInitHandler`
### T080 — Port `AuditLogCommand` → `AuditLogHandler`

Apply canonical port pattern (see WP06).

### T080-bonus — `ConfigCacheDbAuditServiceProvider`

## Definition of Done

- [ ] Five legacy commands deleted, five handlers created.
- [ ] Provider registered.
- [ ] Tests + snapshot tests pass.
- [ ] Full suite green.

## Implementation command

```bash
spec-kitty agent action implement WP17 --agent <name>
```

## Activity Log

- 2026-05-08T15:16:02Z – claude:sonnet:implementer:implementer – shell_pid=977665 – Started implementation via action command
- 2026-05-08T15:32:43Z – claude:sonnet:implementer:implementer – shell_pid=977665 – Ready for review: 5 native handlers, ConfigCacheDbAuditServiceProvider, 5 snapshot tests (all byte-for-byte baseline match), 5 handler unit tests, ghost imports cleared in Phase9/10/20, ConsoleKernel db:init wired to native CliKernel, ServiceProviderContractTest updated. All 4 gates GREEN, 7474 tests 0 errors 0 failures.
