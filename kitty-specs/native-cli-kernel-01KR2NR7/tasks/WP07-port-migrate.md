---
work_package_id: WP07
title: 'Port: Migrate group'
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
- T031
- T032
- T033
- T034
- T035
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/Command/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/Command/Migrate*.php
- packages/cli/src/Command/MigrateDefaults*.php
- packages/cli/src/Command/MigrateRollback*.php
- packages/cli/src/Command/MigrateStatus*.php
- packages/cli/src/Command/Migrate/**
- packages/cli/src/Provider/MigrateServiceProvider.php
- packages/cli/tests/Unit/Command/Migrate*Test.php
- packages/cli/tests/Unit/Command/MigrateDefaults*Test.php
- packages/cli/tests/Unit/Command/MigrateRollback*Test.php
- packages/cli/tests/Unit/Command/MigrateStatus*Test.php
- packages/cli/tests/Unit/Command/Migrate/**
- packages/cli/tests/Integration/Snapshot/Migrate*SnapshotTest.php
tags: []
---

# WP07 — Port: Migrate group

## Branch Strategy

`main` → `main` per lanes.json.

## Objective

Port the migration commands using the canonical pattern from [`WP06`](./WP06-port-health-schema.md). The `Migrate/` helper namespace contains pure utilities (DryRunPlanner, VerifyRunner, etc.) that don't extend Symfony — those stay; only their tests may need migration if they used `CommandTester`.

## Subtasks

### T031 — Port `MigrateCommand` → `MigrateHandler`

Apply canonical port pattern (see WP06). Note: `MigrateCommand` exposes `--dry-run` (NEGATABLE), `--verify`, and the existing `OutputSanitizer` for redacting secrets. Preserve all three behaviours in `MigrateHandler::execute()`. Snapshot fixture: `migrate.help.stdout`.

### T032 — Port `MigrateRollbackCommand` → `MigrateRollbackHandler`

Apply canonical pattern. Preserve `--steps` REQUIRED option.

### T033 — Port `MigrateStatusCommand` → `MigrateStatusHandler`

Apply canonical pattern. Output is a deterministic table; preserve byte-shape via snapshot.

### T034 — Port `MigrateDefaultsCommand` → `MigrateDefaultsHandler`

Apply canonical pattern.

### T035 — Migrate `Migrate/` helper tests if any used `CommandTester`

Run `grep -r CommandTester packages/cli/tests/Unit/Command/Migrate/`. For each match, migrate to `CliTester` per the canonical pattern. Helpers themselves (`DryRunPlanner`, `VerifyRunner`, `OutputSanitizer` etc.) likely don't need any change since they don't extend Symfony.

### T035-bonus — `MigrateServiceProvider`

Create `packages/cli/src/Provider/MigrateServiceProvider.php` implementing `HasNativeCommandsInterface`. Yields four `CommandDefinition`s. Registered in `packages/cli/composer.json` providers list.

## Definition of Done

- [ ] Four legacy `*Command.php` deleted; four `*Handler.php` created.
- [ ] `MigrateServiceProvider` registered.
- [ ] All migrated tests pass.
- [ ] All four snapshot tests pass.
- [ ] CLAUDE.md gotcha respected: `MakeMigrationCommand` (separate from MigrateCommand) requires `$projectRoot` — but that's WP08.
- [ ] Full suite green; gates clean.

## Risks

- **`OutputSanitizer` integration**: this helper currently expects an `OutputInterface`. Adapt to take a `CliIO` or use a thin `WriteSink` typedef interface. Avoid leaking the change into other handlers (it's used only by `MigrateHandler`).
- **`--dry-run` NEGATABLE**: Symfony `--no-dry-run` semantics must round-trip. Confirm via snapshot test.

## Reviewer guidance

- Diff each command's `configure()` against the new CommandDefinition.
- Diff each command's `execute()` body against the new Handler `execute()`.
- Run `bin/waaseyaa migrate --dry-run` and `--no-dry-run` against the snapshot fixture.

## Implementation command

```bash
spec-kitty agent action implement WP07 --agent <name>
```
