---
work_package_id: WP14
title: 'Port: Entity + Type lifecycle'
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
- T063
- T064
- T065
- T066
- T067
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/Command/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/Command/EntityCreate*.php
- packages/cli/src/Command/EntityList*.php
- packages/cli/src/Command/EntityTypeList*.php
- packages/cli/src/Command/TypeEnable*.php
- packages/cli/src/Command/TypeDisable*.php
- packages/cli/src/Provider/EntityTypeServiceProvider.php
- packages/cli/tests/Unit/Command/EntityCreate*Test.php
- packages/cli/tests/Unit/Command/EntityList*Test.php
- packages/cli/tests/Unit/Command/EntityTypeList*Test.php
- packages/cli/tests/Unit/Command/TypeEnable*Test.php
- packages/cli/tests/Unit/Command/TypeDisable*Test.php
- packages/cli/tests/Unit/Command/TypeLifecycleCommandTest.php
- packages/cli/tests/Integration/Snapshot/{EntityCreate,EntityList,EntityTypeList,TypeEnable,TypeDisable}SnapshotTest.php
tags: []
---

# WP14 — Port: Entity + Type lifecycle

## Branch Strategy

`main` → `main` per lanes.json.

## Subtasks

### T063 — Port `EntityCreateCommand` → `EntityCreateHandler`
### T064 — Port `EntityListCommand` → `EntityListHandler`
### T065 — Port `EntityTypeListCommand` → `EntityTypeListHandler`
### T066 — Port `TypeEnableCommand` → `TypeEnableHandler`
### T067 — Port `TypeDisableCommand` → `TypeDisableHandler`

Apply canonical port pattern (see WP06). The existing `TypeLifecycleCommandTest.php` exercises both Enable+Disable interactively; migrate it to a single `TypeLifecycleTest.php` using `CliTester` chaining or rename to two independent tests, whichever the existing structure suggests.

### T067-bonus — `EntityTypeServiceProvider`

Yields five `CommandDefinition`s.

## Definition of Done

- [ ] Five legacy command files deleted; five handlers created.
- [ ] `EntityTypeServiceProvider` registered.
- [ ] All migrated tests pass; lifecycle test still exercises both directions.
- [ ] All five snapshot tests pass.
- [ ] Full suite green; gates clean.

## Risks

- **`enforceIsNew()` gotcha** (CLAUDE.md): `EntityCreateHandler` may need to call `$entity->enforceIsNew()` if it constructs entities with pre-set IDs.

## Implementation command

```bash
spec-kitty agent action implement WP14 --agent <name>
```
