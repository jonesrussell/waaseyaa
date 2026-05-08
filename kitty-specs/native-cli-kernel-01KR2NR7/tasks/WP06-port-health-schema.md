---
work_package_id: WP06
title: 'Port: Health & Schema'
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
- T026
- T027
- T028
- T029
- T030
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/Command/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/Command/HealthCheck*.php
- packages/cli/src/Command/HealthReport*.php
- packages/cli/src/Command/SchemaCheck*.php
- packages/cli/src/Command/SchemaList*.php
- packages/cli/src/Provider/HealthSchemaServiceProvider.php
- packages/cli/tests/Unit/Command/HealthCheck*Test.php
- packages/cli/tests/Unit/Command/HealthReport*Test.php
- packages/cli/tests/Unit/Command/SchemaCheck*Test.php
- packages/cli/tests/Unit/Command/SchemaList*Test.php
- packages/cli/tests/Integration/Snapshot/HealthCheckSnapshotTest.php
- packages/cli/tests/Integration/Snapshot/HealthReportSnapshotTest.php
- packages/cli/tests/Integration/Snapshot/SchemaCheckSnapshotTest.php
- packages/cli/tests/Integration/Snapshot/SchemaListSnapshotTest.php
tags: []
---

# WP06 — Port: Health & Schema

## Branch Strategy

`main` → `main` per lanes.json.

## Objective

Port the four operator-diagnostics commands (Health/Schema). They are first because spec FR-015 makes their stdout/exit-code contracts the strictest. Successful port establishes the canonical pattern for WPs 07–22.

## Context

- Spec FR-015: command names + arg/option signatures + JSON envelopes + exit codes are FROZEN.
- Bulk-edit map: `serialized_keys: do_not_change`, `cli_commands: do_not_change` — JSON envelope keys and command names are operator contracts.
- Snapshot fixtures captured by WP01: `packages/cli/tests/Fixtures/snapshots/{health:check,health:report,schema:check,schema:list}.help.{stdout,stderr,exit}`.

## The canonical port pattern (template for all port WPs)

For each `<Name>Command extends Command`:

1. **Read the existing `configure()`** in the legacy file. Note: command name, description, every argument/option name + mode + description + default, the `--help` body.
2. **Read the existing `execute(Input, Output)`**. Identify dependencies pulled from constructor.
3. **Create `<Name>Handler.php`** — a `final class` with the same constructor (DI-injected services) and a public method `execute(CliIO $io): int`. Translate:
   - `$input->getArgument('foo')` → `$io->getArgument('foo')`.
   - `$input->getOption('bar')` → `$io->getOption('bar')`.
   - `$output->writeln('…')` → `$io->writeln('…')`.
   - `$output->getErrorOutput()->writeln('…')` → `$io->error('…')`.
   - `$io->ask(new Question('…', $default))` → `$io->ask('…', $default)`.
   - Symfony helpers like `Table`, `ProgressBar` are out of scope; emit plain text.
4. **Add a `CommandDefinition` literal** in the WP's ServiceProvider. Construct using named params, declaring args/options exactly as the legacy `configure()` did.
5. **Migrate the test**: rename `<Name>CommandTest.php` → `<Name>HandlerTest.php`; replace `CommandTester` with `CliTester::for($definition, $container)`.
6. **Add a snapshot test** under `tests/Integration/Snapshot/`: load the WP01 fixture, run the handler via CliTester, assert byte-equality of stdout/stderr/exit-code.
7. **Delete the legacy `<Name>Command.php`** in the same commit.

## Subtasks

### T026 — Port HealthCheckCommand → HealthCheckHandler

Apply the canonical pattern. The `--json` option produces a JSON envelope whose keys are frozen. Snapshot test asserts byte-equality.

### T027 — Port HealthReportCommand → HealthReportHandler

Apply the canonical pattern.

### T028 — Port SchemaCheckCommand → SchemaCheckHandler

Apply the canonical pattern. This command has structured diff output; preserve format byte-for-byte.

### T029 — Port SchemaListCommand → SchemaListHandler

Apply the canonical pattern.

### T030 — `HealthSchemaServiceProvider`

Create `packages/cli/src/Provider/HealthSchemaServiceProvider.php` extending `ServiceProvider` and implementing `HasNativeCommandsInterface`. `nativeCommands()` yields four `CommandDefinition`s, one per ported handler. Constructor injection wires the four handlers via `[Handler::class, 'execute']` callables.

Register the provider in the package manifest (`packages/cli/composer.json` already declares the providers list — add the new provider alongside the legacy `CliServiceProvider`).

## Definition of Done

- [ ] Four handler classes exist; four legacy `Command` classes deleted.
- [ ] `HealthSchemaServiceProvider` implements `HasNativeCommandsInterface`.
- [ ] All four `*HandlerTest.php` use `CliTester` (no `CommandTester` import).
- [ ] Four snapshot tests under `tests/Integration/Snapshot/` pass with byte-equality vs WP01 fixtures.
- [ ] `bin/waaseyaa health:check --json` byte-equals WP01 fixture.
- [ ] Full suite green; `composer cs-check`, `composer phpstan`, `bin/check-package-layers` clean.
- [ ] Bulk-edit gate satisfied: no `serialized_keys` (JSON envelope) modified.

## Risks

- **JSON envelope drift.** Mitigation: snapshot tests assert byte-equality; any drift is a port bug, not a snapshot exception.
- **DI re-wiring.** Original commands took dependencies via constructor; handlers do too. Confirm container resolves the handlers correctly through the legacy `bind()` calls (move them from `CliServiceProvider::register()` if needed).

## Reviewer guidance

- Diff the legacy command's `configure()` against the new `CommandDefinition` — every argument and option must transfer.
- Diff the legacy `execute()` against the new `Handler::execute()` — control flow identical, only IO surface swapped.
- Confirm the four legacy files are deleted (`git status` shows them as deleted, not modified).
- Run `bin/waaseyaa health:check --json` post-port and assert byte-equality with WP01 fixture by hand once.

## Implementation command

```bash
spec-kitty agent action implement WP06 --agent <name>
```
