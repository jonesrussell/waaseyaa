---
work_package_id: WP03
title: I/O, Help, Tester
dependencies:
- WP02
requirement_refs:
- FR-007
- FR-008
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T012
- T013
- T014
- T015
- T016
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "881045"
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/Io/**
- packages/cli/src/Help/**
- packages/cli/src/Testing/**
- packages/cli/tests/Unit/Io/**
- packages/cli/tests/Unit/Help/**
- packages/cli/tests/Unit/Testing/**
- packages/cli/tests/Unit/Kernel/HelpRendererTest.php
tags: []
---

# WP03 — I/O, Help, Tester

## Branch Strategy

`main` → `main` per lanes.json.

## Objective

Provide handler authors and test authors with a stable surface: `CliIO`, output writers, the help renderer, and `CliTester`. After this WP, the runtime can dispatch a command end-to-end in a test, but it does not yet plug into `bin/waaseyaa` (WP05) or have a kernel (WP04).

## Context

- Contract: [`contracts/cli-io.md`](../contracts/cli-io.md), [`contracts/cli-tester.md`](../contracts/cli-tester.md).
- TTY behaviour: [`research.md`](../research.md) §R-05.
- Help format: [`research.md`](../research.md) §R-06.

## Subtasks

### T012 — Input/Output writers + Stdin sources

**Files**:
- `packages/cli/src/Io/CliInput.php` — interface (read access to ParsedInput).
- `packages/cli/src/Io/CliOutput.php` — interface with `write(string)` and `writeln(string)`.
- `packages/cli/src/Io/StreamCliOutput.php` — writes to a PHP stream resource.
- `packages/cli/src/Io/BufferedCliOutput.php` — captures into an internal buffer; exposes `getContents(): string`.
- `packages/cli/src/Io/StdinSource.php` — interface with `readLine(): ?string` and `isInteractive(): bool`.
- `packages/cli/src/Io/EmptyStdinSource.php` — always returns null; not interactive.
- `packages/cli/src/Io/StringQueueStdinSource.php` — constructor takes `array $lines`; pops one per `readLine()`; `isInteractive()` returns false (it's deterministic, not a TTY simulator).
- `packages/cli/src/Io/StreamStdinSource.php` — wraps `STDIN`; `isInteractive()` consults T016's TTY detector.

**Tests**: `tests/Unit/Io/BufferedCliOutputTest.php`, `StringQueueStdinSourceTest.php`, `StreamCliOutputTest.php`, `EmptyStdinSourceTest.php`. Roughly ~40 lines each.

### T013 — `CliIO` with non-TTY ask/confirm fallback

**Steps**: Implement per [`contracts/cli-io.md`](../contracts/cli-io.md).
- `ask($question, $default)` and `confirm($question, $default)`:
  - If `isInteractive()` → write to stderr, read from `StdinSource`, return value. EOF / empty → default.
  - If NOT interactive → write `waaseyaa-cli: stdin is not a tty; using default for prompt "<question>"` to stderr exactly once, return default. Never block.
- `confirm` accepts `y|yes|Y|YES` → true; `n|no|N|NO` → false; anything else → default.

**Tests**:
- `tests/Unit/Io/CliIOTest.php` — argument/option getters, write/writeln/error routing, ask/confirm in both interactive (StringQueueStdinSource) and non-interactive (EmptyStdinSource) modes.

**Files**: ~150 lines source, ~250 lines tests.

### T014 — `HelpRenderer` + golden tests

**Steps**: Implement per [`research.md`](../research.md) §R-06. Three deterministic sections: Usage, Description, Arguments, Options. Options sorted alphabetically by long name. `--help`, `-v`/`--verbose`, `-q`/`--quiet`, `--no-interaction`, `--version` auto-injected (kernel-level flags; the renderer knows about them).

**Tests**: `tests/Unit/Help/HelpRendererTest.php` with golden fixtures under `tests/Fixtures/help/`. Each fixture is a markdown-style triple: command definition → expected stdout. Cover: no-arg command, command with required+optional+array args, command with NONE/REQUIRED/OPTIONAL/ARRAY/NEGATABLE options, command with shortcuts.

**Files**: `packages/cli/src/Help/HelpRenderer.php` (~180 lines), test (~120 lines + 6 golden files ~80 lines total).

### T015 — `CliTester`

**Steps**: Implement per [`contracts/cli-tester.md`](../contracts/cli-tester.md). `CliTester::for($def, $container, $stdin = null)` builds a single-command registry, instantiates a kernel-shaped dispatcher *that doesn't depend on `CliKernel` proper* (so this WP can land before WP04). Concretely: a `MiniDispatcher` private class inside `Testing/` that does parse → invoke handler → return int. WP04 will deprecate-and-remove `MiniDispatcher` in favour of `CliKernel`; until then `CliTester` uses both, gated by feature flag.

> Implementer's note: feel free to inline a minimal dispatch path here (parser + handler invocation). It's a temporary shape; WP04 swaps it for the real kernel without changing `CliTester`'s public API.

**Tests**: `tests/Unit/Testing/CliTesterTest.php`:
- Round-trip a fake command, assert exit code 0, captured stdout.
- `executeMap(['name' => 'foo', '--shout' => true])` translates to argv correctly.
- `StringQueueStdinSource(['yes'])` answers a `confirm()` prompt.
- Two `execute()` calls on the same tester are independent.

**Files**: `packages/cli/src/Testing/CliTester.php` (~180 lines), tests (~250 lines).

### T016 — TTY detection helper

**Steps**: Implement `Waaseyaa\Cli\Io\TtyDetector::isInteractive($stream): bool`. Detection priority documented in [`research.md`](../research.md) §R-05:
1. `function_exists('posix_isatty') && posix_isatty($stream)`.
2. `function_exists('stream_isatty') && stream_isatty($stream)`.
3. fallback `false`.

**Files**: `packages/cli/src/Io/TtyDetector.php` (~30 lines), `tests/Unit/Io/TtyDetectorTest.php` (~50 lines using `tmpfile()` for predictable non-TTY streams).

## Definition of Done

- [ ] All listed files exist.
- [ ] `composer cs-check`, `composer phpstan`, `bin/check-package-layers` all clean.
- [ ] `vendor/bin/phpunit packages/cli/tests/Unit/Io/ packages/cli/tests/Unit/Help/ packages/cli/tests/Unit/Testing/` passes.
- [ ] `CliTester` round-trips a fake `CommandDefinition` with both closure and `[FQN, method]` handler shapes.
- [ ] No Symfony imports in any new file.

## Risks

- **`MiniDispatcher` becoming permanent.** Mitigation: WP04 is gated on its removal; reviewer in WP04 verifies `MiniDispatcher` is gone.
- **TTY detection on Windows.** Out of scope (Waaseyaa supports Linux/macOS per CLAUDE.md). Document that.

## Reviewer guidance

- HelpRenderer goldens are byte-equal stable; option sort is alphabetic.
- CliTester captures must reset between `execute()` calls.
- No accidental dep on `Symfony\Component\Console\…`.

## Implementation command

```bash
spec-kitty agent action implement WP03 --agent <name>
```

## Activity Log

- 2026-05-08T03:36:12Z – claude:sonnet:implementer:implementer – shell_pid=878165 – Started implementation via action command
- 2026-05-08T03:49:18Z – claude:sonnet:implementer:implementer – shell_pid=878165 – Ready for review: ConsoleCliIO, HelpRenderer, CliTester, all IO primitives. 60 new tests, 7411 total green.
- 2026-05-08T03:49:43Z – claude:opus-4-7:reviewer:reviewer – shell_pid=881045 – Started review via action command
- 2026-05-08T03:51:24Z – claude:opus-4-7:reviewer:reviewer – shell_pid=881045 – Review passed: ConsoleCliIO correctly implements WP02 CliIO; CliTester resets state between execute() calls and captures stdout/stderr separately + interleaved; HelpRenderer deterministic with alphabetical sort; TtyDetector suppression scoped to posix_isatty only; public-surface-map additions narrowly scoped to WP02+WP03 public types. Unused ArgumentMode import in CliTester is lint noise. Pre-existing WP02 namespace casing wart (Waaseyaa\Cli vs Waaseyaa\CLI) not introduced here; flag for cleanup before WP04. 60 new tests green, 7411 total.
- 2026-05-08T18:06:10Z – claude:opus-4-7:reviewer:reviewer – shell_pid=881045 – Done override: Mission merged to main (cc36dfcd2)
