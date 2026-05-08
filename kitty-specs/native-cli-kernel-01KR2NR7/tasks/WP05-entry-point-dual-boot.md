---
work_package_id: WP05
title: Entry-point + Dual-Boot Adapter
dependencies:
- WP04
requirement_refs:
- FR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T022
- T023
- T024
- T025
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "896443"
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/Compat/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/CliApplication.php
- packages/cli/src/Compat/**
- bin/waaseyaa
- packages/cli/tests/Integration/DualBootTest.php
tags: []
---

# WP05 — Entry-point + Dual-Boot Adapter

## Branch Strategy

`main` → `main` per lanes.json.

## Objective

Rewire `bin/waaseyaa` onto `CliApplication`. Introduce a **temporary** dual-boot adapter that wraps existing `Symfony\Component\Console\Command\Command` instances (registered via the legacy `HasCommandsInterface`) into `CommandDefinition`s so they keep working through the new kernel between WPs 06–22. The adapter is **deleted** in WP23.

## Context

- This is the load-bearing WP for keeping the tree green between WPs 06–22 (each port WP migrates one domain group at a time).
- C-006 forbids permanent shims; this WP introduces the only one allowed by the plan, with explicit removal in WP23.
- Plan §Complexity-Tracking documents this trade.

## Subtasks

### T022 — `CliApplication::main()`

**Steps**: Create `packages/cli/src/CliApplication.php` (final, readonly). It:
- Boots the application kernel (existing framework bootstrap).
- Resolves the `CliKernel` from the container.
- Calls `$kernel->run(array_slice($_SERVER['argv'], 1))`.
- Calls `exit($code)`.

`main()` is the only place `exit()` is called in the CLI surface.

**Files**: ~80 lines.

### T023 — Rewrite `bin/waaseyaa`

**Steps**: Replace the current contents of `bin/waaseyaa` with a minimal bootstrap that:
1. Includes Composer autoload.
2. Sets up environment (`APP_ENV` resolution per AbstractKernel).
3. Calls `CliApplication::main()`.

No `use Symfony\Component\Console\…` imports remain in this file. The dual-boot adapter (T024) does the Symfony glue inside `packages/cli/src/Compat/`, not in `bin/waaseyaa`.

**Files**: `bin/waaseyaa` (~25 lines).

### T024 — Legacy Symfony command adapter

**Steps**: Create `packages/cli/src/Compat/LegacySymfonyCommandAdapter.php` and `packages/cli/src/Compat/LegacySymfonyCommandRegistrar.php`.

`LegacySymfonyCommandAdapter::adapt(SymfonyCommand $cmd): CommandDefinition`:
1. Reads the Symfony `InputDefinition` via reflection / `getDefinition()`.
2. Maps each Symfony `InputArgument` to an `ArgumentDefinition`.
3. Maps each Symfony `InputOption` to an `OptionDefinition`. For modes:
   - `VALUE_NONE` → `OptionMode::None`
   - `VALUE_REQUIRED` → `OptionMode::Required`
   - `VALUE_OPTIONAL` → `OptionMode::Optional`
   - `VALUE_IS_ARRAY | VALUE_REQUIRED` → `OptionMode::Array_`
   - `VALUE_NEGATABLE` (Symfony 5.1+) → `OptionMode::Negatable`
4. Builds a closure handler that, given a `CliIO`:
   - Constructs a `Symfony\Component\Console\Input\ArrayInput` from the `CliIO`'s parsed args/options.
   - Constructs a `Symfony\Component\Console\Output\BufferedOutput` (buffered so we can pipe to `CliIO::write`).
   - Calls `$cmd->run($input, $output)`, returns the int.
   - Streams the buffered output back to `CliIO::write` (line-by-line so progressive output works).

`LegacySymfonyCommandRegistrar` iterates providers implementing the legacy `HasCommandsInterface`, adapts each command, and registers the resulting `CommandDefinition`s into the registry.

`CliKernelServiceProvider` (from WP04) is amended to call `LegacySymfonyCommandRegistrar` AFTER iterating native providers, so name collisions surface as `DuplicateCommandException` (correct behaviour: a command must be ported, not double-registered).

**Files**: `packages/cli/src/Compat/LegacySymfonyCommandAdapter.php` (~180 lines), `packages/cli/src/Compat/LegacySymfonyCommandRegistrar.php` (~80 lines).

> **Important**: This whole `Compat/` directory is deleted in WP23. Mark every file with a top-of-file docblock:
>
> ```php
> /**
>  * @internal Temporary dual-boot bridge. Deleted in WP23 (mission native-cli-kernel-01KR2NR7).
>  *           Do NOT depend on this from application code.
>  */
> ```

### T025 — Integration test: dual-boot end-to-end

**Steps**: `packages/cli/tests/Integration/DualBootTest.php`:
- Boot the framework with two providers:
  - One implementing `HasNativeCommandsInterface` yielding a `native:hello` command.
  - One implementing the legacy `HasCommandsInterface` yielding a Symfony command `legacy:hello`.
- `CliKernel::run(['native:hello', '--name=russell'])` returns 0, stdout contains "russell".
- `CliKernel::run(['legacy:hello', '--name=russell'])` returns 0, stdout contains "russell".
- `CliKernel::run([])` lists both commands.
- Re-run `bin/waaseyaa list` against a fixture providers.json and confirm both surface.

**Files**: ~200 lines test.

## Definition of Done

- [ ] `bin/waaseyaa` boots `CliApplication`; no Symfony imports in the bootstrap file.
- [ ] All currently-shipped Symfony Console commands continue to run via the dual-boot adapter (existing Symfony-based command tests still pass).
- [ ] DualBootTest passes.
- [ ] `vendor/bin/phpunit` full suite passes.
- [ ] `composer cs-check`, `composer phpstan` clean.
- [ ] Adapter files carry the "deleted in WP23" docblock.

## Risks

- **Buffered output fidelity.** Streaming Symfony `BufferedOutput` line-by-line into `CliIO` may distort progress bars or ANSI sequences. Acceptable: the goal is correct stdout/stderr per snapshot tests captured in WP01; progress bars are out of scope (deferred).
- **Adapter becomes permanent.** Mitigation: WP23 deletes `packages/cli/src/Compat/`; WP25 grep gates on `git ls-files packages/cli/src/Compat | wc -l == 0`.
- **Symfony command ctor injection.** Some Symfony commands take services via constructor. Adapter handles only command instances already produced by their providers; the provider does the wiring. Adapter doesn't `new` Symfony commands itself.

## Reviewer guidance

- Confirm bin/waaseyaa is ~25 lines; if larger, push logic into `CliApplication`.
- Confirm `Compat/` files have the `@internal Deleted in WP23` docblock.
- Confirm DualBootTest exercises BOTH paths.
- Re-read C-006 in spec.md; this WP is the documented exception.

## Implementation command

```bash
spec-kitty agent action implement WP05 --agent <name>
```

## Activity Log

- 2026-05-08T04:18:54Z – claude:sonnet:implementer:implementer – shell_pid=887676 – Started implementation via action command
- 2026-05-08T04:28:32Z – claude:sonnet:implementer:implementer – shell_pid=887676 – Ready for review: CliApplication static entry point, dual-boot adapter (LegacySymfonyCommandAdapter + LegacySymfonyCommandRegistrar), rewritten bin/waaseyaa, DualBootTest — all 7446 tests green
- 2026-05-08T04:29:02Z – claude:opus-4-7:reviewer:reviewer – shell_pid=889589 – Started review via action command
- 2026-05-08T04:31:52Z – claude:opus-4-7:reviewer:reviewer – shell_pid=889589 – Moved to planned
- 2026-05-08T04:32:26Z – claude:sonnet:implementer:implementer – shell_pid=890379 – Started implementation via action command
- 2026-05-08T04:53:06Z – claude:sonnet:implementer:implementer – shell_pid=890379 – Cycle 2 fix: bridge wired, casing fixed, contract restored
- 2026-05-08T04:53:45Z – claude:opus-4-7:reviewer:reviewer – shell_pid=896443 – Started review via action command
- 2026-05-08T04:56:56Z – claude:opus-4-7:reviewer:reviewer – shell_pid=896443 – Review passed cycle 2: bridge wired, gates green, casing fixed, contract restored
