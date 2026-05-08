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
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T089
- T090
- T091
- T092
- T093
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "998000"
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

## Activity Log

- 2026-05-08T16:16:23Z – claude:sonnet:implementer:implementer – shell_pid=994802 – Started implementation via action command
- 2026-05-08T16:29:10Z – claude:sonnet:implementer:implementer – shell_pid=994802 – Ready for review: 5 commands ported (About/AdminBuild/AdminDev/DebugContext/EventList) via MiscAServiceProvider; all 4 gates green; 7498/7498 tests pass; phpstan [OK] No errors; 5 snapshot fixtures verified byte-for-byte
- 2026-05-08T16:29:42Z – claude:opus-4-7:reviewer:reviewer – shell_pid=998000 – Started review via action command
- 2026-05-08T16:31:27Z – claude:opus-4-7:reviewer:reviewer – shell_pid=998000 – Review passed: 5 commands ported (about/admin:build/admin:dev/debug:context/event:list); WP01 baseline fixtures byte-identical; container-bound EventListHandler resolves Symfony EventDispatcher correctly; gates green (phpunit 7498/0, phpstan OK, cs-check clean); baseline cleanup matches deleted Command files.
- 2026-05-08T18:06:42Z – claude:opus-4-7:reviewer:reviewer – shell_pid=998000 – Done override: Mission merged to main (cc36dfcd2)
