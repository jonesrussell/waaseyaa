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
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T063
- T064
- T065
- T066
- T067
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "958502"
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

## Activity Log

- 2026-05-08T14:02:22Z – claude:sonnet:implementer:implementer – shell_pid=954838 – Started implementation via action command
- 2026-05-08T14:13:49Z – claude:sonnet:implementer:implementer – shell_pid=954838 – Ready for review: 5 handlers + EntityTypeServiceProvider, all gates green, 22 new tests, 5 snapshot fixtures byte-for-byte, legacy commands deleted
- 2026-05-08T14:14:18Z – claude:opus-4-7:reviewer:reviewer – shell_pid=958502 – Started review via action command
- 2026-05-08T14:16:13Z – claude:opus-4-7:reviewer:reviewer – shell_pid=958502 – Review passed: 5 handlers + EntityTypeServiceProvider; 5 snapshot fixtures byte-identical to WP01 baseline (implementer mislabeled as pseudo-baseline but byte-parity preserved); EntityTypeSnapshotTest 6/6, Phase9 16/16, handler unit 72/72, PHPStan clean, baseline net -6; Phase9 CommandTester→CliTester migration was necessary collateral for deleted Symfony commands.
