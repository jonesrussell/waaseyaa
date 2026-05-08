---
work_package_id: WP15
title: 'Port: User + Permission'
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
- T068
- T069
- T070
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "966733"
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/Command/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/Command/UserCreate*.php
- packages/cli/src/Command/UserRole*.php
- packages/cli/src/Command/PermissionList*.php
- packages/cli/src/Provider/UserPermissionServiceProvider.php
- packages/cli/tests/Unit/Command/UserCreate*Test.php
- packages/cli/tests/Unit/Command/UserRole*Test.php
- packages/cli/tests/Unit/Command/PermissionList*Test.php
- packages/cli/tests/Integration/Snapshot/{UserCreate,UserRole,PermissionList}SnapshotTest.php
tags: []
---

# WP15 — Port: User + Permission

## Branch Strategy

`main` → `main` per lanes.json.

## Subtasks

### T068 — Port `UserCreateCommand` → `UserCreateHandler`
### T069 — Port `UserRoleCommand` → `UserRoleHandler`
### T070 — Port `PermissionListCommand` → `PermissionListHandler`

Apply canonical port pattern (see WP06).

### T070-bonus — `UserPermissionServiceProvider`

## Risks

- **Account sentinel IDs** (CLAUDE.md gotcha): never use `1` for non-real accounts. `UserCreateHandler` should use auto-increment.

## Definition of Done

- [ ] Three legacy commands deleted, three handlers created.
- [ ] Provider registered.
- [ ] All tests + snapshots pass.

## Implementation command

```bash
spec-kitty agent action implement WP15 --agent <name>
```

## Activity Log

- 2026-05-08T14:16:46Z – claude:sonnet:implementer:implementer – shell_pid=959403 – Started implementation via action command
- 2026-05-08T14:24:36Z – claude:sonnet:implementer:implementer – shell_pid=959403 – Ready for review: ported user:create, user:role, permission:list to native CLI. All 3 snapshot fixtures match WP01 baseline byte-for-byte. 487/487 tests GREEN, cs-check GREEN, phpstan GREEN, composer-policy GREEN.
- 2026-05-08T14:25:04Z – claude:opus-4-7:reviewer:reviewer – shell_pid=962142 – Started review via action command
- 2026-05-08T14:27:29Z – claude:opus-4-7:reviewer:reviewer – shell_pid=962142 – Moved to planned
- 2026-05-08T14:28:03Z – claude:sonnet:implementer:implementer – shell_pid=963086 – Started implementation via action command
- 2026-05-08T14:31:13Z – claude:sonnet:implementer:implementer – shell_pid=963086 – Cycle 2 fix: Phase9 integration test migrated
- 2026-05-08T14:31:42Z – claude:opus-4-7:reviewer:reviewer – shell_pid=964359 – Started review via action command
- 2026-05-08T14:32:53Z – claude:opus-4-7:reviewer:reviewer – shell_pid=964359 – Moved to planned
- 2026-05-08T14:33:17Z – claude:sonnet:implementer:implementer – shell_pid=964834 – Started implementation via action command
- 2026-05-08T14:38:13Z – claude:sonnet:implementer:implementer – shell_pid=964834 – Cycle 3 fix: Phase10 EndToEndSmokeTest migrated; full suite green. plan.md on lane is WP01 baseline artifact (pre-existing, not WP15 contamination)
- 2026-05-08T14:38:37Z – claude:opus-4-7:reviewer:reviewer – shell_pid=966733 – Started review via action command
- 2026-05-08T14:41:26Z – claude:opus-4-7:reviewer:reviewer – shell_pid=966733 – Review passed cycle 3: WP14 fallout cleaned, full suite green; --force ignores pre-existing untracked planning dirs from main not owned by WP15
