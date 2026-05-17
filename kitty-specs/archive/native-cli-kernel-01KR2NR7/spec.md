# Native CLI Kernel

**Mission ID**: `01KR2NR7GYWJKD6CPSN9P2FPC2` · **mid8**: `01KR2NR7`
**Slug**: `native-cli-kernel-01KR2NR7`
**Mission type**: software-dev
**Change mode**: `bulk_edit` (rename target: `Symfony\Component\Console\…` → `Waaseyaa\Cli\…`; full classification in `occurrence_map.yaml` produced during `/spec-kitty.plan`)
**Branch contract**: start `main` → planning base `main` → merge target `main`

---

## 1. Why this mission exists

Waaseyaa is a framework. Its CLI surface is currently rented from `symfony/console`: every shipped command extends `Symfony\Component\Console\Command\Command`, the registry (`CliCommandRegistry`) wraps a Symfony `Application`, and the `bin/waaseyaa` entry-point boots `WaaseyaaApplication` (a Symfony subclass). That coupling is incidental, not load-bearing — Waaseyaa already owns its own DI container, service providers, event dispatcher, HTTP kernel, and middleware pipeline. The CLI is the only top-level surface still leased from another framework.

The cost of that lease is real:
- Provider capability contracts (`HasCommandsInterface`) leak Symfony types into Layer 0 (`packages/foundation/`), so any package wiring commands must require Symfony Console even when it has no other reason to.
- The framework's CLI ergonomics, output formatting, exit-code semantics, and testing affordances are not under Waaseyaa's control. Consumers extending Waaseyaa inherit Symfony Console's surface area whether they want it or not.
- `CommandTester` couples test code to a transitive concrete from a foreign framework.

This mission terminates that lease by shipping a Waaseyaa-native CLI runtime and porting every first-party command onto it in a single hard cut.

## 2. Scope

### In scope

- A new Waaseyaa-native CLI runtime in `packages/cli/`:
  - `CliKernel` — argv parsing, command lookup, dispatch, exit-code propagation.
  - `CliApplication` — top-level entry-point bootstrapping the runtime.
  - `CommandRegistry` — registry of `CommandDefinition` records, resolves by name.
  - `CommandDefinition` — DTO: name, description, arguments, options, handler.
  - `ArgumentDefinition` — name, mode (`REQUIRED` | `OPTIONAL`), description, default, `array` flag.
  - `OptionDefinition` — long/short name, mode (`NONE` | `REQUIRED` | `OPTIONAL` | `ARRAY` | `NEGATABLE`), description, default.
  - `CliIO` — input/output abstraction: positional args, options, `write`/`writeln`/`error`/`ask`/`confirm`.
  - `CliInput` / `CliOutput` interfaces backing `CliIO`.
  - `CliTester` — testing harness replacing `Symfony\Component\Console\Tester\CommandTester`.
- A new provider capability `HasNativeCommandsInterface` in `packages/foundation/` returning `iterable<CommandDefinition>`. The old `HasCommandsInterface` is **removed** (hard cut).
- Every shipped first-party command (≈74 production classes across `packages/cli/` and `packages/northcloud/`) is rewritten as a final, strict-typed handler returning an int exit code, registered via `HasNativeCommandsInterface`. Inventory is enumerated and tracked in the implementation plan.
- `bin/waaseyaa` rewires onto `CliApplication`. No Symfony imports remain in the boot path.
- `packages/cli/composer.json` drops `symfony/console` from runtime `require` (may stay in `require-dev` only if a test fixture explicitly justifies it; default is to drop entirely).
- All command tests are migrated from `CommandTester` to `CliTester`.
- New spec at `docs/specs/cli-kernel.md` documenting the runtime contract, parser semantics, exit-code policy, and migration story.
- Existing specs that reference Symfony Console (notably `docs/specs/operator-diagnostics.md` and any `packages/cli/` doc) updated to reference the native kernel.
- `bin/check-package-layers` and `composer phpstan` continue to pass.
- Composer policy continues to pass (`bin/check-composer-policy`): no `@dev`, no wildcard internal constraints, `self.version` only in root.

### Out of scope (deferred to follow-up missions if needed)

- Shell completion (bash/zsh/fish completion scripts).
- Progress bars, spinners, table renderers — `CliIO` exposes only line-oriented output. A separate package (e.g. `waaseyaa/cli-ui`) can layer on top.
- Interactive multi-step wizards beyond simple `ask`/`confirm`.
- ANSI-aware string-width measurement (handled by terminal where required).
- Application-layer code (Giiken, Minoo, etc.) — none touched.

### Explicitly NOT changed

- The HTTP kernel (`packages/foundation/src/Kernel/HttpKernel.php`).
- The DI container, service-provider lifecycle, event dispatcher.
- Migration runner internals (`Migrator`, `MigrationLoader`); only the `Migrate*Command` thin wrappers move.
- The 7-layer architecture (the new runtime stays in Layer 6 — Interfaces — exactly where `packages/cli/` already lives).

## 3. Stakeholders & users

| Actor | What they care about |
|---|---|
| Framework operator (running `bin/waaseyaa` in production) | Same command names, same exit codes, same observable output as today. No script breakage. |
| Application developer (consumes Waaseyaa) | Provider authoring stays simple: register a command via `HasNativeCommandsInterface` with no Symfony import. |
| Extension author (third-party package shipping commands) | A documented, stable provider contract; the SDK story stays coherent with `docs/specs/external-extension-sdk.md`. |
| Test author | A drop-in `CliTester` that exposes stdin/stdout/stderr capture, exit code, and option/argument injection. |
| Operator-diagnostics consumer | `Health*` and `SchemaCheck*` commands keep producing structured output compatible with the existing operator-diagnostics spec contract. |

## 4. User scenarios

**Scenario A — Operator runs a health check.**
The operator executes `bin/waaseyaa health:check --json`. The native kernel parses argv, resolves the command, instantiates the handler via the DI container, runs it, and emits the same JSON envelope as today on stdout. Exit code is `0` on success, non-zero on failure, identical to the current behaviour documented in `docs/specs/operator-diagnostics.md`.

**Scenario B — Developer registers a new command.**
A developer adds a `ServiceProvider` that implements `HasNativeCommandsInterface::nativeCommands(): iterable<CommandDefinition>`. They construct `CommandDefinition` records inline, naming a closure or `[$this, 'method']` handler. No Symfony imports appear in their package. The command is auto-discovered at boot and listed by `bin/waaseyaa list`.

**Scenario C — Test asserts command output.**
A unit test instantiates `CliTester::for($commandDefinition, $container)`, calls `->execute(['--name' => 'foo', 'pkg' => 'bar'])`, and asserts `->getExitCode() === 0`, `->getStdout()` contains the expected string, `->getStderr()` is empty. No Symfony test classes referenced.

**Scenario D — Developer types `bin/waaseyaa --help` or `bin/waaseyaa some:cmd --help`.**
The kernel emits a usage block listing arguments, options (with short forms), defaults, and the description. Exit code `0`.

**Scenario E — Argv edge cases.**
- `bin/waaseyaa cmd -- --not-an-option positional` — everything after `--` is treated as positional.
- `bin/waaseyaa cmd --opt=value` and `bin/waaseyaa cmd --opt value` are equivalent for `REQUIRED`-mode options.
- `bin/waaseyaa cmd -abc` for `NONE`-mode short flags is equivalent to `-a -b -c`.
- `bin/waaseyaa cmd --no-foo` toggles a `NEGATABLE` `--foo` option to `false`.
- `bin/waaseyaa cmd --tag=a --tag=b` accumulates into `['a', 'b']` for an `ARRAY`-mode option.
- Unknown option → exit code `2`, helpful error on stderr, no stack trace.
- Missing required argument → exit code `2`, error names the argument.

## 5. Functional Requirements

| ID | Requirement | Status |
|---|---|---|
| FR-001 | `CliKernel::run(argv): int` MUST parse argv, resolve a `CommandDefinition` from `CommandRegistry`, invoke its handler with a `CliIO`, and return an integer exit code. | required |
| FR-002 | The argv parser MUST support: required & optional positional arguments; `array` positional (collects remaining); `--long`, `-s` options in `NONE` / `REQUIRED` / `OPTIONAL` / `ARRAY` / `NEGATABLE` modes; `--key=value` and `--key value` equivalence for required-value options; `--` end-of-options sentinel; stacked short flags for `NONE`-mode (`-abc` ≡ `-a -b -c`); `--no-foo` for `NEGATABLE`. | required |
| FR-003 | Unknown commands, unknown options, missing required arguments, and type-coercion failures MUST exit with code `2` and a single-line error on stderr; no PHP stack trace surfaces unless `--verbose` is passed. | required |
| FR-004 | `CommandRegistry` MUST resolve by exact command name and return a deterministic listing for `bin/waaseyaa list`. Names follow the `domain:action` convention already in use (e.g. `health:check`). | required |
| FR-005 | `CommandDefinition` is an immutable record with: `string $name`, `string $description`, `list<ArgumentDefinition> $arguments`, `list<OptionDefinition> $options`, `\Closure(CliIO):int $handler` (closures may be produced from `[$service, 'method']` via `\Closure::fromCallable`). | required |
| FR-006 | `HasNativeCommandsInterface::nativeCommands(): iterable<CommandDefinition>` is the sole provider contract for command registration. The old `HasCommandsInterface` is removed and any caller is migrated. | required |
| FR-007 | `CliIO` MUST expose: `getArgument(string)`, `getOption(string)`, `hasOption(string)`, `getArguments(): array`, `getOptions(): array`, `write(string)`, `writeln(string='')`, `error(string)`, `ask(string $question, ?string $default=null): string`, `confirm(string $question, bool $default=false): bool`. | required |
| FR-008 | `CliTester` MUST allow synchronous execution of a `CommandDefinition` against a container, capture stdout & stderr, expose exit code, and accept argument/option injection both as argv arrays and as a typed associative array. | required |
| FR-009 | `bin/waaseyaa` MUST boot the application kernel, gather all `HasNativeCommandsInterface` providers, build a `CommandRegistry`, and pass control to `CliKernel`. No `use Symfony\Component\Console` statement remains in the boot path. | required |
| FR-010 | Every first-party command currently extending `Symfony\Component\Console\Command\Command` MUST be ported to a native handler returning `int`, registered via `HasNativeCommandsInterface`. The exhaustive inventory is enumerated in the plan; no command is silently dropped. | required |
| FR-011 | `packages/cli/composer.json` MUST NOT list `symfony/console` in runtime `require`. The mission's final WP gates this with a check (grep + `composer why symfony/console` clean). | required |
| FR-012 | All migrated command tests MUST pass under PHPUnit with no `Symfony\Component\Console\Tester\CommandTester` references in first-party code. | required |
| FR-013 | `docs/specs/cli-kernel.md` MUST exist and document: parser semantics, exit-code policy, the provider contract, the testing harness, and the layer-graph placement of `packages/cli/`. | required |
| FR-014 | `docs/specs/operator-diagnostics.md` MUST be updated to reference `CliKernel`/`CommandDefinition` instead of Symfony Console for the Health/SchemaCheck command surface. | required |
| FR-015 | The migrated `Health:check`, `Health:report`, `Schema:check`, `Schema:list`, `Migrate`, `Migrate:rollback`, `Migrate:status`, `Make:migration`, and the remaining shipped commands MUST keep their existing **command names**, **argument names**, **option names**, **stdout shape**, and **exit codes** (no externally visible behavioural change beyond the runtime swap). | required |

## 6. Non-Functional Requirements

| ID | Requirement | Threshold | Status |
|---|---|---|---|
| NFR-001 | Cold-start cost of `bin/waaseyaa list` on the dev box | ≤ 110% of the current Symfony-Console baseline measured immediately before the cut (recorded in plan) | required |
| NFR-002 | Runtime memory ceiling for `bin/waaseyaa list` | ≤ current baseline + 4 MiB | required |
| NFR-003 | Test suite for `packages/cli/` after migration | 100% of pre-cut command tests pass; coverage of new parser ≥ 90% lines | required |
| NFR-004 | Layer discipline | `bin/check-package-layers` passes; `packages/foundation/` has zero `use Symfony\Component\Console` imports | required |
| NFR-005 | Composer policy | `bin/check-composer-policy` passes; `symfony/console` absent from `packages/cli/composer.json` runtime `require` | required |
| NFR-006 | Static analysis | `composer phpstan` passes at level 5 with no new baseline entries for the new code | required |
| NFR-007 | Code style | `composer cs-check` passes; new code uses `declare(strict_types=1)`, `final class`, `readonly` properties where applicable, named-parameter constructor calls per Waaseyaa style | required |

## 7. Constraints

| ID | Constraint | Status |
|---|---|---|
| C-001 | PHP 8.4+ only (per `feedback_php_version`). Use readonly classes, native enums, `\Closure::fromCallable`, named constructor params. | binding |
| C-002 | No application code touched. The `apps/`, `bin/` outside `bin/waaseyaa`, and external repos (Giiken, Minoo) are off-limits. | binding |
| C-003 | Layer architecture preserved — `packages/cli/` stays Layer 6 (Interfaces); `HasNativeCommandsInterface` lives in `packages/foundation/` (Layer 0) and references only Layer 0 types (no Symfony Console). | binding |
| C-004 | The hard-cut is atomic: the same merge that adds `CliKernel` removes `HasCommandsInterface` and ports every command. No mixed state on `main`. | binding |
| C-005 | Bulk-edit gate (DIRECTIVE_035): `change_mode: bulk_edit`, `occurrence_map.yaml` produced during plan with all 8 standard categories classified. CLI command names ARE first-party identifiers we control and ARE renamed if-and-only-if a name is itself improved (default: keep names). | binding |
| C-006 | No backward-compatibility shim: `HasCommandsInterface` is removed, not deprecated. Per CLAUDE.md "Don't add backwards-compatibility shims when you can just change the code." | binding |
| C-007 | No `psr/log` introduced. New code uses `Waaseyaa\Foundation\Log\LoggerInterface` if logging is required. | binding |
| C-008 | Ingestion defaults, security defaults, mail stack, and middleware pipeline contracts are untouched. | binding |
| C-009 | The `northcloud` package's `NcSyncCommand` is migrated as part of the bulk edit. The northcloud provider continues to register through the same provider lifecycle. | binding |

## 8. Assumptions

- `symfony/console` is the only Symfony component being severed in this mission. `symfony/event-dispatcher`, `symfony/routing`, `symfony/uid`, `symfony/yaml`, `symfony/validator`, `symfony/messenger` remain framework deps.
- `WaaseyaaApplication` (the current Symfony `Application` subclass) is removed entirely; `CliApplication` is the only top-level entry point class.
- The `PackageManifestCompiler` will discover `HasNativeCommandsInterface` providers the same way it discovers any other capability interface — no new manifest field is required.
- The current `WaaseyaaVersionCommand` reads version from the same source it does today (composer + version-provenance); only the runtime base class changes.
- Operator scripts in production call commands by their public names, not by extending command classes. Renaming the runtime substrate is therefore opaque to them.

## 9. Success Criteria

1. **Symfony Console gone from runtime.** A `composer why symfony/console` from the repo root shows zero first-party (waaseyaa/*) entries; the package may persist transitively only via dev-only chains. Verified by a CI script in the final WP.
2. **All shipped commands runnable.** Every command listed by `bin/waaseyaa list` before the cut is listed by `bin/waaseyaa list` after the cut, with the same name and the same `--help` argument/option signature.
3. **Operator parity.** Running each Health/SchemaCheck/Migrate command against a known fixture produces the same stdout JSON shape and the same exit code as the pre-cut baseline. A snapshot-comparison fixture lives in `packages/cli/tests/Integration/`.
4. **Test parity or better.** PHPUnit reports green on the full suite. Coverage for the new parser is ≥ 90% lines / 80% branches.
5. **Layer & policy gates green.** `bin/check-package-layers`, `bin/check-composer-policy`, `composer phpstan`, `composer cs-check`, `tools/drift-detector.sh` all pass on the merge commit.
6. **Spec lives.** `docs/specs/cli-kernel.md` exists and is reachable from the orchestration table in `CLAUDE.md`. `docs/specs/operator-diagnostics.md` cites it.

## 10. Key entities

| Entity | Role |
|---|---|
| `CommandDefinition` | Immutable record describing a command (name, description, args, options, handler closure). |
| `ArgumentDefinition` | Positional parameter spec: name, mode, default, array flag, description. |
| `OptionDefinition` | Named-flag spec: long name, optional short name, mode, default, description. |
| `CommandRegistry` | Set of `CommandDefinition`s keyed by name; produced once at boot from provider iteration. |
| `CliKernel` | Stateless dispatcher: `(argv, registry, container) → int`. |
| `CliIO` | Per-invocation context: parsed args, parsed options, writers for stdout/stderr, prompts. |
| `CliApplication` | Top-level entry-point binding the kernel to PHP's process surface (`$_SERVER['argv']`, `STDIN/STDOUT/STDERR`, `exit($code)`). |
| `HasNativeCommandsInterface` | Provider capability contract: `nativeCommands(): iterable<CommandDefinition>`. |
| `CliTester` | Test harness wrapping `CliKernel` with capture buffers and structured assertions. |

## 11. Dependencies

- Composer policy script (`bin/check-composer-policy`).
- Layer-check script (`bin/check-package-layers`).
- `packages/foundation/` provider lifecycle (`ServiceProvider`, manifest compilation).
- DI container (`Container::resolve`) for handler instantiation when handlers are class-method references.
- `LoggerInterface` from foundation for any structured logging emitted from kernel internals.
- Drift detector (`tools/drift-detector.sh`) for spec-staleness gating.

## 12. Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| A migrated command silently changes its `--help` signature, breaking an operator script. | Medium | High | Snapshot-test every command's `--help` output pre-cut and assert byte-equality post-cut. |
| The argv parser misses a Symfony Console edge case used by exactly one shipped command. | Medium | Medium | Enumerate every option/argument shape in the inventory; build the parser test matrix from the inventory, not from imagination. |
| Bulk-edit gate flags log/telemetry strings that look like command names. | Low | Low | `occurrence_map.yaml` will set `logs_telemetry: do_not_change` per the skill default; CLI command names live under `cli_commands` and stay unchanged. |
| Hidden caller of removed `HasCommandsInterface` outside `packages/`. | Low | Medium | Repo-wide grep included in final WP gating. |
| `symfony/console` re-enters as a transitive dep through another package. | Low | Low | Final WP gate runs `composer why symfony/console` and asserts only test-only / unrelated chains remain. |
| Loss of progress-bar / table helpers degrades operator UX for long commands. | Medium | Low | Out of scope for this mission; if pain emerges, follow-up `waaseyaa/cli-ui` package layers on top of `CliIO`. |

## 13. Bulk-edit declaration

This mission is `change_mode: bulk_edit`. The rename target is:

- **Old**: `Symfony\Component\Console\…` (imports, base class, application class, tester) and `Waaseyaa\Foundation\ServiceProvider\Capability\HasCommandsInterface`.
- **New**: `Waaseyaa\Cli\…` runtime types and `Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface`.

Per-category default actions (final classification deferred to `occurrence_map.yaml` in plan):

| Category | Action | Reasoning |
|---|---|---|
| `code_symbols` | `rename` | Internal; we own them. |
| `import_paths` | `rename` | Must track symbol renames. |
| `filesystem_paths` | `manual_review` | Test fixture paths under `packages/cli/tests/` may move; review case-by-case. |
| `serialized_keys` | `do_not_change` | Health/SchemaCheck JSON envelopes are operator contracts. |
| `cli_commands` | `do_not_change` | Public command names (`health:check`, `migrate`, etc.) are scripts' contract. |
| `user_facing_strings` | `rename_if_user_visible` | `--help` text mentioning "Symfony" rewritten; internal labels preserved. |
| `tests_fixtures` | `rename` | Tests must reflect the new runtime. |
| `logs_telemetry` | `do_not_change` | Existing log channels and metric labels are observability contracts. |

## 14. Acceptance review (post-merge)

After merge, the mission-review skill (`spec-kitty-mission-review`) verifies:
- All FR/NFR/C items above hold against the merged code.
- `docs/specs/cli-kernel.md` matches the implemented runtime.
- `docs/specs/operator-diagnostics.md` no longer references Symfony Console.
- `composer why symfony/console` shows only dev/transitive chains, none through `waaseyaa/cli`.
- Drift detector shows no stale specs touched by the mission.
