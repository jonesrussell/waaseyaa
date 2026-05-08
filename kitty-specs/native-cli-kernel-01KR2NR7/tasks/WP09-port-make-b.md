---
work_package_id: WP09
title: 'Port: Make group B (Provider, Public, Test, EntityType, Plugin)'
dependencies:
- WP08
requirement_refs:
- FR-010
- FR-012
- FR-015
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T042
- T043
- T044
- T045
- T046
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "938063"
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/Command/Make/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/Command/Make/MakeProvider*.php
- packages/cli/src/Command/Make/MakePublic*.php
- packages/cli/src/Command/Make/MakeTest*.php
- packages/cli/src/Command/MakeEntityType*.php
- packages/cli/src/Command/MakePlugin*.php
- packages/cli/src/Provider/MakeServiceProviderB.php
- packages/cli/tests/Unit/Command/Make/MakeProvider*Test.php
- packages/cli/tests/Unit/Command/Make/MakePublic*Test.php
- packages/cli/tests/Unit/Command/Make/MakeTest*Test.php
- packages/cli/tests/Unit/Command/MakeEntityType*Test.php
- packages/cli/tests/Unit/Command/MakePlugin*Test.php
- packages/cli/tests/Integration/Command/Make/MakePublicCommandTest.php
- packages/cli/tests/Integration/Snapshot/Make{Provider,Public,Test,EntityType,Plugin}SnapshotTest.php
tags: []
---

# WP09 — Port: Make group B

## Branch Strategy

`main` → `main` per lanes.json. **Depends on WP08** (uses `AbstractMakeHandler`).

## Subtasks

### T042 — Port `MakeProviderCommand` → `MakeProviderHandler`
### T043 — Port `MakePublicCommand` → `MakePublicHandler`

This command has an integration test (`tests/Integration/Command/Make/MakePublicCommandTest.php`). Migrate it to `CliTester` and rename to `MakePublicHandlerTest.php` per the canonical pattern.

### T044 — Port `MakeTestCommand` → `MakeTestHandler`
### T045 — Port `MakeEntityTypeCommand` → `MakeEntityTypeHandler`
### T046 — Port `MakePluginCommand` → `MakePluginHandler`

Apply canonical port pattern (see WP06).

### T046-bonus — `MakeServiceProviderB`

Yields five `CommandDefinition`s. Registered in `packages/cli/composer.json`.

## Definition of Done

- [ ] Five legacy command files deleted; five handlers created.
- [ ] All tests migrated; all snapshot tests pass.
- [ ] `MakePublicHandlerTest` (migrated from integration test) passes.
- [ ] Full suite green; gates clean.

## Risks

- **`MakeEntityType` and `MakePlugin` are NOT under `Make/` directory** — they're at `packages/cli/src/Command/MakeEntityTypeCommand.php` and `MakePluginCommand.php` (top-level). Ownership glob covers both.

## Reviewer guidance

Same as WP06–WP08.

## Implementation command

```bash
spec-kitty agent action implement WP09 --agent <name>
```

## Activity Log

- 2026-05-08T12:30:05Z – claude:sonnet:implementer:implementer – shell_pid=934354 – Started implementation via action command
- 2026-05-08T12:42:54Z – claude:sonnet:implementer:implementer – shell_pid=934354 – Ready for review: 5 make:* handlers ported, all 4 gates green, per-command diffs empty
- 2026-05-08T12:43:22Z – claude:opus-4-7:reviewer:reviewer – shell_pid=938063 – Started review via action command
- 2026-05-08T12:46:12Z – claude:opus-4-7:reviewer:reviewer – shell_pid=938063 – Review passed: 18/18 ported commands byte-parity (group B make:provider/public/test/entity-type/plugin via AbstractMakeHandler); cs/phpstan/phpunit GREEN (7462 tests); no WP01 fixtures modified; HelpRenderer untouched by WP09; AbstractMakeCommand legacy base deleted.
- 2026-05-08T18:06:22Z – claude:opus-4-7:reviewer:reviewer – shell_pid=938063 – Done override: Mission merged to main (cc36dfcd2)
