---
work_package_id: WP20
title: 'Port: Misc cluster A (About/Admin/Debug/Event)'
dependencies:
- WP05
requirement_refs:
- FR-010
- FR-012
- FR-015
planning_base_branch: main
merge_target_branch: main
branch_strategy: Start `main` → planning base `main` → final merge `main`. Worktree per lanes.json.
subtasks:
- T089
- T090
- T091
- T092
- T093
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/Command/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/Command/About*.php
- packages/cli/src/Command/AdminBuild*.php
- packages/cli/src/Command/AdminDev*.php
- packages/cli/src/Command/DebugContext*.php
- packages/cli/src/Command/EventList*.php
- packages/cli/src/Provider/MiscAServiceProvider.php
- packages/cli/tests/Unit/Command/About*Test.php
- packages/cli/tests/Unit/Command/AdminBuild*Test.php
- packages/cli/tests/Unit/Command/AdminDev*Test.php
- packages/cli/tests/Unit/Command/DebugContext*Test.php
- packages/cli/tests/Unit/Command/EventList*Test.php
- packages/cli/tests/Integration/Snapshot/{About,AdminBuild,AdminDev,DebugContext,EventList}SnapshotTest.php
tags: []
---

# WP20 — Port: Misc cluster A

## Branch Strategy

`main` → `main` per lanes.json.

## Subtasks

### T089 — Port `AboutCommand` → `AboutHandler`
### T090 — Port `AdminBuildCommand` → `AdminBuildHandler`
### T091 — Port `AdminDevCommand` → `AdminDevHandler`
### T092 — Port `DebugContextCommand` → `DebugContextHandler`
### T093 — Port `EventListCommand` → `EventListHandler`

Apply canonical port pattern (see WP06).

### T093-bonus — `MiscAServiceProvider`

## Risks

- `admin:dev` and `admin:build` shell out to npm — preserve the shell-out exactly. Don't introduce new escaping; reuse what's there.

## Definition of Done

- [ ] Five legacy commands deleted, five handlers created.
- [ ] Provider registered.
- [ ] Tests + snapshot tests pass.
- [ ] Full suite green.

## Implementation command

```bash
spec-kitty agent action implement WP20 --agent <name>
```
