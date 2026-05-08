# Implementation Plan: Native CLI Kernel

**Mission**: `native-cli-kernel-01KR2NR7` (mid8 `01KR2NR7`)
**Branch**: `main` (planning base) → merges to `main`
**Date**: 2026-05-08
**Spec**: [`spec.md`](./spec.md)
**Change mode**: `bulk_edit` (occurrence map: [`occurrence_map.yaml`](./occurrence_map.yaml))

## Summary

Replace `symfony/console` as the runtime substrate of `packages/cli/` with a Waaseyaa-native CLI runtime in the same package. Hard-cut migration: `CliKernel` + `CliApplication` + `CommandRegistry` + `CommandDefinition` + argv parser ship together with the rewrite of every shipped command (~74 production classes plus their tests), the removal of `HasCommandsInterface` in favour of `HasNativeCommandsInterface`, the `bin/waaseyaa` entry-point rewire, the drop of `symfony/console` from `packages/cli/composer.json` runtime require, and the new `docs/specs/cli-kernel.md` spec.

## Technical Context

**Language/Version**: PHP 8.4+ (mandatory per `feedback_php_version`; `declare(strict_types=1)` everywhere; `final class`, `readonly` properties, native enums).
**Primary Dependencies (added)**: none beyond what `packages/cli/` already depends on (`waaseyaa/foundation`, `waaseyaa/database-legacy` etc.). The runtime parser is hand-rolled — explicitly no replacement third-party CLI library.
**Primary Dependencies (removed)**: `symfony/console` from runtime `require` of `packages/cli/composer.json`.
**Storage**: N/A (CLI is stateless dispatch).
**Testing**: PHPUnit 10.5 with `#[Test]`/`#[CoversClass]`/`#[CoversNothing]` attributes; new `CliTester` harness replacing `Symfony\Component\Console\Tester\CommandTester`; argv-edge-case matrix in `packages/cli/tests/Unit/Kernel/`; per-command snapshot tests in `packages/cli/tests/Integration/Snapshot/`.
**Target Platform**: PHP CLI SAPI on Linux/macOS; same support matrix as the rest of the framework.
**Project Type**: monorepo package within `waaseyaa/framework`. Layer 6 (Interfaces).
**Performance Goals**: cold-start `bin/waaseyaa list` ≤ 110% of pre-cut baseline; peak memory ≤ baseline + 4 MiB. Baseline captured by **WP-01** before any code change.
**Constraints**: layer discipline (`bin/check-package-layers` clean); composer policy (`bin/check-composer-policy` clean); PHPStan level 5 clean; no `psr/log`; `HasNativeCommandsInterface` lives in Layer 0 with zero Symfony imports.
**Scale/Scope**: ~74 production command classes ported, ~70 command tests migrated, 1 new spec, 1 entry-point script, 1 composer manifest mutation, 1 capability interface added + 1 removed.

### Resolved planning answers (from interrogation)

| Question | Decision |
|---|---|
| Handler shape | **Class-FQN + method name pair**, e.g. `[HealthCheckHandler::class, 'execute']`. `CliKernel` resolves the class via the DI container at dispatch time and invokes the method with `CliIO`. Closures still permitted for inline/test cases via `\Closure::fromCallable`. |
| Non-TTY `ask`/`confirm` | Return the documented default; emit a one-line stderr notice (`waaseyaa-cli: stdin is not a tty; using default for prompt "<question>"`). Never block. |
| Performance baseline | **WP-01** runs `php -d opcache.enable_cli=0 bin/waaseyaa list` 10× pre-cut, records median wall-time and peak memory in this file's `## Performance Baseline` section. Final WP re-runs the same harness post-cut and asserts the threshold. |

## Charter Check

| Charter section | Gate | Status |
|---|---|---|
| Project Charter | Mission furthers framework-internal goal of owning the CLI surface; aligns with DDD paradigm (CLI = adapter to domain). | ✅ |
| Testing Standards | Maintain PHPUnit 10.5 conventions, contract tests for capability interfaces, in-memory storage where applicable. | ✅ — applied to all new tests. |
| Quality Gates | `composer cs-check`, `composer phpstan`, `bin/check-package-layers`, `bin/check-composer-policy`, `tools/drift-detector.sh`. | ✅ — gating final WP. |
| Performance Benchmarks | NFR-001/002 captured via WP-01 baseline + WP-final assertion. | ✅ |
| Branch Strategy | Start `main` → land `main`. Mission gets a single integration branch per Spec Kitty conventions. | ✅ |
| Governance Activation / Policy / Directives (DIR-001/002/003) | DIR-035 (bulk-edit) governs this mission; `change_mode: bulk_edit` set; map produced and validated. | ✅ |

**Re-check after Phase 1**: planned at the bottom of this file (Phase 1 design did not violate any gate).

## Performance Baseline

> **Captured during WP-01 (pre-cut). Recorded here verbatim from the run.**
> Until WP-01 runs, the table below is empty — do not invent numbers.

| Metric | Pre-cut value | Post-cut threshold | Post-cut value | Pass? |
|---|---|---|---|---|
| `bin/waaseyaa list` wall-time (median of 10) | _captured by WP-01_ | ≤ 110% of pre-cut | _captured by WP-final_ | _WP-final_ |
| `bin/waaseyaa list` peak memory | _captured by WP-01_ | ≤ pre-cut + 4 MiB | _captured by WP-final_ | _WP-final_ |
| `bin/waaseyaa health:check --json` wall-time (median of 10) | _captured by WP-01_ | ≤ 110% of pre-cut | _captured by WP-final_ | _WP-final_ |

The harness script `kitty-specs/native-cli-kernel-01KR2NR7/scripts/perf-harness.sh` is created by WP-01 and lives there for the duration of the mission. It is deleted in WP-final after numbers are recorded.

## Project Structure

### Documentation (this mission)

```
kitty-specs/native-cli-kernel-01KR2NR7/
├── spec.md                # /spec-kitty.specify output (DONE)
├── plan.md                # this file
├── research.md            # Phase 0 output
├── data-model.md          # Phase 1 output
├── quickstart.md          # Phase 1 output
├── contracts/
│   ├── cli-kernel.md           # CliKernel public surface
│   ├── command-definition.md   # CommandDefinition / Argument / Option records
│   ├── cli-io.md               # CliIO read/write surface
│   ├── has-native-commands.md  # HasNativeCommandsInterface contract
│   └── cli-tester.md           # CliTester harness contract
├── checklists/
│   └── requirements.md    # spec quality checklist (DONE)
├── occurrence_map.yaml    # bulk-edit classification (DONE, schema-valid)
├── tasks/                 # populated by /spec-kitty.tasks
└── meta.json              # mission metadata (DONE)
```

### Source code (repository root)

```
packages/cli/
├── composer.json                 # symfony/console removed from runtime require (WP-final)
├── src/
│   ├── CliApplication.php        # NEW: top-level entry-point class
│   ├── CliKernel.php             # NEW: argv → exit-code dispatcher
│   ├── CommandDefinition.php     # NEW: immutable record
│   ├── ArgumentDefinition.php    # NEW: positional spec
│   ├── OptionDefinition.php      # NEW: named-flag spec
│   ├── CommandRegistry.php       # NEW: keyed set of CommandDefinitions
│   ├── ArgumentMode.php          # NEW: enum REQUIRED/OPTIONAL
│   ├── OptionMode.php            # NEW: enum NONE/REQUIRED/OPTIONAL/ARRAY/NEGATABLE
│   ├── Io/
│   │   ├── CliIO.php             # NEW: per-invocation context
│   │   ├── CliInput.php          # NEW: stdin/argv reader
│   │   ├── CliOutput.php         # NEW: stdout/stderr writer
│   │   └── BufferedCliOutput.php # NEW: capture impl for CliTester
│   ├── Parser/
│   │   ├── ArgvParser.php        # NEW: argv → ParsedInput
│   │   ├── ParsedInput.php       # NEW: parse result record
│   │   └── ParseError.php        # NEW: structured error
│   ├── Provider/
│   │   └── CliKernelServiceProvider.php  # NEW: registers CliKernel + registry build
│   ├── Testing/
│   │   └── CliTester.php         # NEW: replaces Symfony CommandTester
│   ├── Help/
│   │   └── HelpRenderer.php      # NEW: --help output
│   ├── Command/                  # PORTED: every existing command rewritten as a final handler
│   │   ├── HealthCheckHandler.php
│   │   ├── HealthReportHandler.php
│   │   ├── SchemaCheckHandler.php
│   │   ├── … (full list in tasks WP scoping)
│   ├── Provenance/ComposerProvenanceReporter.php  # PORTED: stays, no longer extends Symfony
│   └── (REMOVED) WaaseyaaApplication.php
│   └── (REMOVED) CliCommandRegistry.php
├── tests/
│   ├── Unit/
│   │   ├── Kernel/
│   │   │   ├── ArgvParserTest.php          # NEW: edge-case matrix
│   │   │   ├── CliKernelTest.php           # NEW: dispatch + exit codes
│   │   │   ├── CommandRegistryTest.php     # NEW
│   │   │   ├── CommandDefinitionTest.php   # NEW
│   │   │   └── HelpRendererTest.php        # NEW
│   │   ├── Io/
│   │   │   ├── CliIOTest.php               # NEW
│   │   │   └── BufferedCliOutputTest.php   # NEW
│   │   └── Command/                        # PORTED: 1:1 with the handler files above
│   ├── Integration/
│   │   ├── Provider/
│   │   │   └── ProviderRegistersCommandsTest.php  # NEW: full provider→registry→dispatch
│   │   └── Snapshot/
│   │       ├── HealthCheckSnapshotTest.php # NEW: byte-equal stdout vs pre-cut fixture
│   │       └── … (one per public-contract command per FR-015)
│   └── Fixtures/
│       └── snapshots/                      # NEW: captured by WP-01 from current Symfony runtime
└── README.md                     # UPDATED: replaces Symfony Console references

packages/foundation/
├── src/ServiceProvider/Capability/
│   ├── HasNativeCommandsInterface.php  # NEW
│   └── HasCommandsInterface.php        # REMOVED (FR-006, C-006)
└── tests/Unit/ServiceProvider/Capability/
    └── HasNativeCommandsInterfaceTest.php  # NEW: contract test

packages/northcloud/
└── src/Command/NcSyncHandler.php   # PORTED from NcSyncCommand
└── src/Provider/NorthCloudServiceProvider.php  # UPDATED: implements HasNativeCommandsInterface

bin/
└── waaseyaa                         # REWIRED: boots CliApplication, no Symfony imports

docs/specs/
├── cli-kernel.md                    # NEW (FR-013)
└── operator-diagnostics.md          # UPDATED (FR-014): cite CliKernel
```

**Structure Decision**: `packages/cli/` keeps its existing position (Layer 6 — Interfaces). The new runtime classes live under `Waaseyaa\Cli\…` (root namespace already owned by the package). The capability interface lives in `packages/foundation/` under `Waaseyaa\Foundation\ServiceProvider\Capability\` — same neighbourhood as the existing capability surface. Northcloud's command moves to a handler class without changing the package's location.

## Bulk-edit gate

`occurrence_map.yaml` validated against `src/doctrine/schemas/occurrence-map.schema.yaml`. Result: **valid** (no errors). All 8 standard categories explicit. Public command names, JSON envelopes, and observability labels classified `do_not_change`. Per-path exceptions named for the targeted rewrite points (`composer.json`, `HasCommandsInterface.php`, `WaaseyaaApplication.php`, `CliCommandRegistry.php`, `bin/waaseyaa`, the two specs).

## Phase 0 — Research

See [`research.md`](./research.md) for the Phase 0 output. The decisions captured there:

- **Handler shape**: class-FQN + method name (DI-resolved), closure-permitted.
- **Argv parser semantics**: reproduce the Symfony Console subset documented in §5/FR-002 of the spec — short stacking for NONE-only, `--key=value` and `--key value` equivalent for REQUIRED, `--`/end-of-options, `--no-foo` toggling NEGATABLE, ARRAY accumulation. Explicitly NOT supported: short-option value-attaching like `-fbar` (only `-f bar` and `-f=bar` for REQUIRED-mode short flags), and Symfony's input-validator/option-aliases features (out of scope).
- **Exit-code contract**: 0 success, 1 handler-reported failure, 2 parse error (unknown command, unknown option, missing required arg, type-coercion failure), 130 SIGINT.
- **CommandTester replacement**: `CliTester` accepts `(CommandDefinition $def, ContainerInterface $container, ?StdinSource $stdin = null)`, exposes `execute(array $argv): self`, `getExitCode()`, `getStdout()`, `getStderr()`. Stdin is injectable; default is `EmptyStdinSource`.
- **Help formatting**: deterministic three-section block — `Usage:` line, `Arguments:` table, `Options:` table. Long-form descriptions wrap at 80 cols. Verified with golden snapshots.
- **TTY detection**: `posix_isatty(STDIN)` when `posix` is loaded; fallback to `stream_isatty(STDIN)`; final fallback to `defined('STDIN') ? @stream_get_meta_data(STDIN)['stream_type'] !== 'STDIO' : false`. Behaviour documented in spec §4 Scenario E and FR-007.
- **Per-command port strategy**: WPs grouped by domain (Health, Schema, Migrate, Make, Optimize, Queue, Telescope, Ingest, Entity, User, Workflow, Cache/Db/Schedule, Misc) so each WP touches a coherent slice and can be reviewed independently.

## Phase 1 — Design

Outputs (one file each):
- [`data-model.md`](./data-model.md) — entity/record-shape definitions (CommandDefinition, ArgumentDefinition, OptionDefinition, ParsedInput, ParseError, CommandRegistry).
- [`contracts/cli-kernel.md`](./contracts/cli-kernel.md) — `CliKernel` public method surface.
- [`contracts/command-definition.md`](./contracts/command-definition.md) — record shapes and invariants.
- [`contracts/cli-io.md`](./contracts/cli-io.md) — `CliIO` interface contract.
- [`contracts/has-native-commands.md`](./contracts/has-native-commands.md) — provider capability contract.
- [`contracts/cli-tester.md`](./contracts/cli-tester.md) — testing harness contract.
- [`quickstart.md`](./quickstart.md) — short walkthrough: register a command, dispatch via CliKernel, test it with CliTester.

## Charter re-check (post-Phase 1)

| Section | Status | Notes |
|---|---|---|
| Quality Gates | ✅ | All new code subject to `composer cs-check`, `phpstan`, layer + policy checks. |
| Testing Standards | ✅ | Contract tests for `HasNativeCommandsInterface` and `CliIO` planned; PHPUnit 10.5 attributes used throughout. |
| Performance Benchmarks | ✅ | Baseline + post-cut harness sequenced; thresholds in plan. |
| DIR-035 (bulk-edit) | ✅ | Map valid; categories explicit; exceptions named. |

No new violations introduced during design.

## WP sequencing (preview — full materialization in `/spec-kitty.tasks`)

The tasks command will materialise individual WP files. The intended ordering:

| # | WP theme | Outputs |
|---|---|---|
| WP-01 | Performance baseline | Run perf harness 10× against current Symfony runtime; record numbers in `## Performance Baseline`; commit harness script under `kitty-specs/.../scripts/`; capture per-command stdout snapshots into `packages/cli/tests/Fixtures/snapshots/` for later byte-equality assertions. |
| WP-02 | Native runtime core | `CliKernel`, `CommandRegistry`, `CommandDefinition`, `ArgumentDefinition`, `OptionDefinition`, `ArgumentMode`, `OptionMode`, `Parser/ArgvParser`, `Parser/ParsedInput`, `Parser/ParseError` + their unit tests (parser edge-case matrix). No commands ported yet; suite stays green via existing Symfony commands. |
| WP-03 | I/O + help + tester | `Io/CliIO`, `Io/CliInput`, `Io/CliOutput`, `Io/BufferedCliOutput`, `Help/HelpRenderer`, `Testing/CliTester` + their unit tests. |
| WP-04 | Provider capability | `HasNativeCommandsInterface` in foundation; contract test; `CliKernelServiceProvider` wires registry from provider iteration. `HasCommandsInterface` not yet removed. |
| WP-05 | Entry-point + dual-boot | `bin/waaseyaa` rewires onto `CliApplication`. The application discovers both `HasCommandsInterface` (legacy Symfony commands) and `HasNativeCommandsInterface` (new) — at this stage the legacy adapter exists *only* in `bin/waaseyaa` to keep CI green between WPs. NOT a permanent shim; deleted in the final WP per C-004/C-006. |
| WP-06 | Port — Health & SchemaCheck | `Health*Handler`, `Schema*Handler` ported, tests migrated to `CliTester`, snapshot tests pass. |
| WP-07 | Port — Migrate & Make | `Migrate*Handler`, `Make*Handler` ported, tests migrated. |
| WP-08 | Port — Optimize, Queue, Schedule, Cache, Db | Domain group port + tests. |
| WP-09 | Port — Telescope, Ingest, Search, Semantic, Audit, Perf | Domain group port + tests. |
| WP-10 | Port — Entity, Type, User, Permission, Route, Event, Config, Workflow, Bundle, Fixture, Relationship, Extension, Plugin, Debug, Sync, Install, Serve, About, Version, ScaffoldAuth, Admin*, Provenance | Final domain port + tests. |
| WP-11 | Port — Northcloud `NcSyncCommand` | Single-command WP isolated for clean review. |
| WP-12 | Hard-cut | Remove `HasCommandsInterface`, `WaaseyaaApplication`, `CliCommandRegistry`, the legacy-adapter branch in `bin/waaseyaa`. Drop `symfony/console` from `packages/cli/composer.json`. Run `composer why symfony/console` and assert no first-party-runtime chain. |
| WP-13 | Spec authoring & cross-refs | `docs/specs/cli-kernel.md` written; `docs/specs/operator-diagnostics.md` updated; CLAUDE.md orchestration table extended with `cli-kernel.md`; `tools/drift-detector.sh` clean. |
| WP-14 | Final perf + parity verification | Re-run harness; assert NFR-001/002 thresholds met; assert all snapshot tests pass; full suite green; quality gates green. |

WPs 06–10 can run on parallel lanes once WPs 02–05 are merged (each command port is locally independent). WP-12/13/14 are strictly sequential after WP-11.

## Complexity Tracking

| Violation | Why needed | Simpler alternative rejected because |
|---|---|---|
| WP-05 introduces a temporary dual-boot adapter for legacy Symfony commands | Without it, the integration branch sits broken between WP-05 and WP-11 — every command port lands on a red tree, defeating the bulk-edit gate's per-WP review. | Big-bang single-WP port: too large to review, exceeds Spec Kitty's per-WP scope norms, makes `composer test` red for the entire WP series. |
| Hand-rolled argv parser (vs. adopting an existing minimal library like `garden-cli` or `chopper`) | The mission's premise is that Waaseyaa owns its CLI surface end-to-end; introducing another third-party CLI dep substitutes one lease for another. | Vendoring a small lib would be ~half the parser code but adds a dep, a license, and a future migration risk; rejected. |

The WP-05 dual-boot is fully removed in WP-12 — no permanent shim survives merge per C-006.

---

**End of plan.** Phase 0 (`research.md`) and Phase 1 (`data-model.md`, `contracts/`, `quickstart.md`) are written as sibling files. Next command: `/spec-kitty.tasks` to materialise WPs.
