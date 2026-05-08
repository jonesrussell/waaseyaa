---
work_package_id: WP04
title: Provider Capability + CliKernel
dependencies:
- WP02
- WP03
requirement_refs:
- FR-001
- FR-003
- FR-006
- FR-008
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T017
- T018
- T019
- T020
- T021
agent: "claude:sonnet:implementer:implementer"
shell_pid: "881621"
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/CliKernel.php
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/foundation/src/ServiceProvider/Capability/HasNativeCommandsInterface.php
- packages/foundation/tests/Unit/ServiceProvider/Capability/HasNativeCommandsInterfaceTest.php
- packages/cli/src/CliKernel.php
- packages/cli/src/Provider/CliKernelServiceProvider.php
- packages/cli/tests/Integration/CliKernelDispatchTest.php
- packages/cli/tests/Integration/ProviderRegistersCommandsTest.php
tags: []
---

# WP04 — Provider Capability + CliKernel

## Branch Strategy

`main` → `main` per lanes.json.

## Objective

1. Land `HasNativeCommandsInterface` in foundation (Layer 0).
2. Wire it into manifest discovery.
3. Implement `CliKernel::run(argv): int` proper.
4. Replace `CliTester`'s temporary `MiniDispatcher` (introduced in WP03) with the real kernel.

After this WP, the runtime is feature-complete; only `bin/waaseyaa` remains to be wired (WP05).

## Context

- Contracts: [`contracts/has-native-commands.md`](../contracts/has-native-commands.md), [`contracts/cli-kernel.md`](../contracts/cli-kernel.md).
- Layer discipline: `HasNativeCommandsInterface` in Layer 0; uses string FQN reference to `\Waaseyaa\Cli\CommandDefinition`. CLAUDE.md gotcha: "When cross-layer attribute scanning is needed, use string constants instead of `::class` references".

## Subtasks

### T017 — Add `HasNativeCommandsInterface`

**Steps**: Create `packages/foundation/src/ServiceProvider/Capability/HasNativeCommandsInterface.php`:

```php
<?php
declare(strict_types=1);

namespace Waaseyaa\Foundation\ServiceProvider\Capability;

interface HasNativeCommandsInterface
{
    /** @return iterable<\Waaseyaa\Cli\CommandDefinition> */
    public function nativeCommands(): iterable;
}
```

**Files**: 1 new file, ~12 lines.

### T018 — Contract test

**Steps**: `packages/foundation/tests/Unit/ServiceProvider/Capability/HasNativeCommandsInterfaceTest.php`:
- Reflection assertion: interface declares `nativeCommands` returning `iterable`.
- File-source assertion: `file_get_contents($path)` does NOT contain `'use Symfony\\'`.
- Stub provider implementing the interface yields a valid `CommandDefinition`.

**Files**: ~80 lines test.

### T019 — Manifest capability scan

**Steps**: Locate `PackageManifestCompiler` in `packages/foundation/`. Add `HasNativeCommandsInterface` to its capability list using a string constant (per CLAUDE.md gotcha):

```php
private const CAPABILITY_HAS_NATIVE_COMMANDS = 'Waaseyaa\\Foundation\\ServiceProvider\\Capability\\HasNativeCommandsInterface';
```

The compiler iterates registered providers; for each, it checks `class_implements()` against the capability list and emits manifest entries. Add a unit test under `tests/Unit/ServiceProvider/PackageManifestCompilerTest.php` (or extend existing) asserting the new capability is detected.

**Files**: edit existing PackageManifestCompiler.php (~10 line additions), extend its test (~30 line additions).

> Note: PackageManifestCompiler is owned by foundation, not packages/cli. This WP lists it under `owned_files` as a targeted edit; if Spec Kitty's ownership detector flags an overlap with another mission's WP, that's a real conflict — report it.

### T020 — `CliKernelServiceProvider::buildRegistry()`

**Steps**: Create `packages/cli/src/Provider/CliKernelServiceProvider.php` extending `ServiceProvider`. In `register()`:
- Bind `CliKernel` as a singleton.
- Bind a `CommandRegistry` factory that iterates registered providers, calls `nativeCommands()` on those implementing the interface, accumulates yielded `CommandDefinition`s, validates uniqueness, and returns the registry.

The factory is invoked exactly once per process (memoised in the provider).

**Files**: ~120 lines source.

### T021 — `CliKernel::run()` + integration tests

**Steps**: Implement per [`contracts/cli-kernel.md`](../contracts/cli-kernel.md).
- Constructor: `CommandRegistry`, `ContainerInterface`, `HelpRenderer`, two `CliOutput` (stdout/stderr), `StdinSource`, optional `LoggerInterface`.
- `run(array $argv): int`:
  1. Detect `--version` → print version, return 0.
  2. Detect `--help` or empty argv → render the LIST of all registered commands, return 0.
  3. Otherwise pop command name, look up in registry. Miss → stderr "Unknown command: $name", return 2.
  4. Detect `--help` in remaining argv → render help for that command, return 0.
  5. Parse remaining argv via `ArgvParser`. Parse error → stderr formatted message, return 2. With `--verbose`, also print full trace.
  6. Build `CliIO`. Invoke handler closure. Catch any uncaught throwable → stderr "<Class>: <msg>" + (trace if --verbose), return 1.
  7. SIGINT handler if `pcntl` is loaded → return 130.
  8. Return handler's int.

**Tests**: `packages/cli/tests/Integration/CliKernelDispatchTest.php` and `ProviderRegistersCommandsTest.php`:
- Register a fake provider yielding 2 commands.
- Build registry via `CliKernelServiceProvider`.
- `CliKernel::run(['cmd-a', '--name=foo'])` returns expected exit code, expected stdout.
- Empty argv shows listing.
- Unknown command exits 2.
- Parse error exits 2.
- Handler throw exits 1 with class+message on stderr.
- Handler returning 0/1/2 propagates correctly.

**Files**: `packages/cli/src/CliKernel.php` (~250 lines), test files (~300 lines combined).

**Also**: edit `packages/cli/src/Testing/CliTester.php` to switch from WP03's `MiniDispatcher` to the real `CliKernel`. Confirm WP03's tests still pass unchanged.

## Definition of Done

- [ ] `HasNativeCommandsInterface` exists in foundation; layer-check clean.
- [ ] `PackageManifestCompiler` recognises the new capability.
- [ ] `CliKernel::run()` covers all 9 behaviours from contracts/cli-kernel.md.
- [ ] `CliTester` no longer references `MiniDispatcher`.
- [ ] Integration tests in `tests/Integration/` pass.
- [ ] `composer phpstan`, `composer cs-check`, `bin/check-package-layers` clean.

## Risks

- **Layer violation in PackageManifestCompiler.** Mitigation: capability is a string FQN constant; compiler does NOT `use \Waaseyaa\Cli\…`.
- **Memoisation correctness.** If the registry factory accidentally instantiates twice, port WPs may double-register commands. Cover with a test that calls the factory twice and asserts identity.

## Reviewer guidance

- Read `contracts/cli-kernel.md` and `contracts/has-native-commands.md` first.
- Verify `MiniDispatcher` from WP03 is gone (`grep -r MiniDispatcher packages/cli` empty).
- Verify `packages/foundation/` does not depend on `waaseyaa/cli` in composer.json.
- Verify reflection-only access in PackageManifestCompiler.

## Implementation command

```bash
spec-kitty agent action implement WP04 --agent <name>
```

## Activity Log

- 2026-05-08T03:51:52Z – claude:sonnet:implementer:implementer – shell_pid=881621 – Started implementation via action command
- 2026-05-08T04:08:07Z – claude:sonnet:implementer:implementer – shell_pid=881621 – Ready for review: CliKernel::run() + HasNativeCommandsInterface + manifest scan + CliKernelServiceProvider + 29 tests all green (7440 suite-wide)
