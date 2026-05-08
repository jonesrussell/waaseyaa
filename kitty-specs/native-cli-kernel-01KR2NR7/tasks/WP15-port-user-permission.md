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
agent: "claude:sonnet:implementer:implementer"
shell_pid: "959403"
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
