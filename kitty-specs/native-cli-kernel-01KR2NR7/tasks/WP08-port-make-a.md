---
work_package_id: WP08
title: 'Port: Make group A (Entity, Job, Listener, Migration, Policy)'
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
- T036
- T037
- T038
- T039
- T040
- T041
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/Command/Make/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/Command/Make/AbstractMakeCommand.php
- packages/cli/src/Command/Make/AbstractMakeHandler.php
- packages/cli/src/Command/Make/MakeEntity*.php
- packages/cli/src/Command/Make/MakeJob*.php
- packages/cli/src/Command/Make/MakeListener*.php
- packages/cli/src/Command/Make/MakeMigration*.php
- packages/cli/src/Command/Make/MakePolicy*.php
- packages/cli/src/Provider/MakeServiceProviderA.php
- packages/cli/tests/Unit/Command/Make/MakeEntity*Test.php
- packages/cli/tests/Unit/Command/Make/MakeJob*Test.php
- packages/cli/tests/Unit/Command/Make/MakeListener*Test.php
- packages/cli/tests/Unit/Command/Make/MakeMigration*Test.php
- packages/cli/tests/Unit/Command/Make/MakePolicy*Test.php
- packages/cli/tests/Integration/Snapshot/Make{Entity,Job,Listener,Migration,Policy}SnapshotTest.php
tags: []
---

# WP08 — Port: Make group A

## Branch Strategy

`main` → `main` per lanes.json.

## Objective

Refactor the `AbstractMakeCommand` shared base into a Symfony-free `AbstractMakeHandler` POPO, then port the first five Make commands onto it.

## Subtasks

### T036 — Refactor `AbstractMakeCommand` → `AbstractMakeHandler`

**Steps**:
1. Inspect `AbstractMakeCommand`: it presumably extends `Symfony\Console\Command\Command` and provides shared scaffolding helpers (path resolution, stub rendering, file writing).
2. Extract its non-Symfony helpers into `AbstractMakeHandler` (no parent class — it's a base for composition or extension).
3. Where it called `$this->ask()` / `$this->writeln()` etc., switch to receiving a `CliIO` (or take it as a constructor param if every concrete handler will share the same instance).
4. Delete `AbstractMakeCommand.php`.

**Files**: `AbstractMakeHandler.php` (~150 lines), tests if any (~80 lines).

### T037–T041 — Port five Make commands

For each of `MakeEntity`, `MakeJob`, `MakeListener`, `MakeMigration`, `MakePolicy`:

Apply the canonical port pattern (see [WP06](./WP06-port-health-schema.md) §"The canonical port pattern"). Each handler extends/uses `AbstractMakeHandler`. CLAUDE.md gotchas to honour:

- `MakeMigrationHandler` MUST take `string $projectRoot` constructor param (per CLAUDE.md gotcha — keep behaviour).
- `MakeMigrationCommand` previously didn't implement `--package` flag (issue #464). Don't add it here; preserve current behaviour.

### T041-bonus — `MakeServiceProviderA`

Yields the five `CommandDefinition`s. Registered in `packages/cli/composer.json`.

## Definition of Done

- [ ] `AbstractMakeCommand` deleted; `AbstractMakeHandler` exists.
- [ ] Five legacy command files deleted; five handlers created.
- [ ] `MakeServiceProviderA` registered.
- [ ] All migrated tests + snapshot tests pass.
- [ ] `MakeMigrationHandler` takes `$projectRoot` per gotcha.
- [ ] Full suite green; gates clean.

## Risks

- **AbstractMakeHandler shared with WP09.** WP09 uses `AbstractMakeHandler` — its merge MUST come after this WP. Sequenced via `dependencies: ["WP08"]` on WP09.

## Reviewer guidance

- Confirm `AbstractMakeHandler` carries no `Symfony\` imports.
- Confirm gotcha-preserving constructors are intact.

## Implementation command

```bash
spec-kitty agent action implement WP08 --agent <name>
```
