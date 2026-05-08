# Tasks — Native CLI Kernel

**Mission**: `native-cli-kernel-01KR2NR7` (mid8 `01KR2NR7`)
**Spec**: [`spec.md`](./spec.md) · **Plan**: [`plan.md`](./plan.md) · **Map**: [`occurrence_map.yaml`](./occurrence_map.yaml)
**Branch contract**: start `main` → land `main`.
**Change mode**: `bulk_edit` — finalize-tasks will not start any WP until the bulk-edit gate is satisfied (it is — schema-validated).

> Total scope: 25 work packages, ~140 subtasks. The plan's 14-WP outline has been refined into 25 right-sized WPs (3-7 subtasks each) so each WP is independently implementable in one session, owned_files do not overlap, and per-domain ports run on parallel lanes.

## Subtask Index

| ID | Description | WP | Parallel |
|----|---|----|----|
| T001 | Capture pre-cut wall-time + memory baseline for `bin/waaseyaa list` and `health:check` (10 runs each, median) | WP01 | — | [D] |
| T002 | Commit perf-harness script under `kitty-specs/.../scripts/` | WP01 | [D] |
| T003 | Capture stdout/stderr/exit-code snapshots for every shipped public command | WP01 | [D] |
| T004 | Record numbers in `plan.md` § Performance Baseline | WP01 | — | [D] |
| T005 | Verify snapshot fixture set is comprehensive (one per public command from `bin/waaseyaa list`) | WP01 | — | [D] |
| T006 | Implement `ArgumentMode` and `OptionMode` enums | WP02 | [D] |
| T007 | Implement `ArgumentDefinition` readonly record + invariant tests | WP02 | [D] |
| T008 | Implement `OptionDefinition` readonly record + invariant tests | WP02 | [D] |
| T009 | Implement `CommandDefinition` (handler normalisation via `\Closure::fromCallable`) + invariant tests | WP02 | — | [D] |
| T010 | Implement `CommandRegistry` (register/get/all/names, sorted, duplicate guard) + tests | WP02 | — | [D] |
| T011 | Implement `ArgvParser` + `ParsedInput` + `ParseError` + full edge-case test matrix (FR-002) | WP02 | — | [D] |
| T012 | Implement `CliInput`, `CliOutput`, `BufferedCliOutput`, `StdinSource` + `EmptyStdinSource` + `StringQueueStdinSource` | WP03 | [D] |
| T013 | Implement `CliIO` (argument/option getters, write/writeln/error, ask/confirm with non-TTY fallback) + tests | WP03 | — | [D] |
| T014 | Implement `HelpRenderer` (Usage/Description/Arguments/Options sections, deterministic output) + golden tests | WP03 | [D] |
| T015 | Implement `CliTester::for/execute/executeMap/getExitCode/getStdout/getStderr/getOutput` + tests | WP03 | — | [D] |
| T016 | TTY detection helper using `posix_isatty` → `stream_isatty` → false fallback | WP03 | [D] |
| T017 | Add `HasNativeCommandsInterface` to `packages/foundation/src/ServiceProvider/Capability/` (no Symfony imports) | WP04 | — | [D] |
| T018 | Contract test asserting interface file has zero `Symfony\` imports + iterable return type | WP04 | [D] |
| T019 | Extend `PackageManifestCompiler` capability list to recognise the new interface | WP04 | — | [D] |
| T020 | Implement `CliKernelServiceProvider::buildRegistry()` iterating providers via manifest | WP04 | — | [D] |
| T021 | Implement `CliKernel::run(argv): int` (dispatch, exit codes, signal handling) + integration tests | WP04 | — | [D] |
| T022 | Implement `CliApplication::main()` entry-point class (no Symfony import) | WP05 | [D] |
| T023 | Rewrite `bin/waaseyaa` to boot `CliApplication`; include temporary dual-boot adapter that ALSO discovers `HasCommandsInterface` providers | WP05 | — | [D] |
| T024 | Add legacy adapter: wraps Symfony `Command` instances into `CommandDefinition` shims so they keep running through `CliKernel` while WPs 06-22 progress | WP05 | — | [D] |
| T025 | Integration test: dual-boot serves both legacy Symfony commands and a single new native command end-to-end | WP05 | — | [D] |
| T026 | Port `HealthCheckCommand` → `HealthCheckHandler` + migrate test + snapshot pass | WP06 | [D] |
| T027 | Port `HealthReportCommand` → `HealthReportHandler` + migrate test + snapshot pass | WP06 | [D] |
| T028 | Port `SchemaCheckCommand` → `SchemaCheckHandler` + migrate test + snapshot pass | WP06 | [D] |
| T029 | Port `SchemaListCommand` → `SchemaListHandler` + migrate test + snapshot pass | WP06 | [D] |
| T030 | Update `packages/cli` ServiceProvider to register the four handlers via `HasNativeCommandsInterface` | WP06 | — | [D] |
| T031 | Port `MigrateCommand` → `MigrateHandler` + migrate test + snapshot pass | WP07 | [D] |
| T032 | Port `MigrateRollbackCommand` → `MigrateRollbackHandler` + migrate test + snapshot pass | WP07 | [D] |
| T033 | Port `MigrateStatusCommand` → `MigrateStatusHandler` + migrate test + snapshot pass | WP07 | [D] |
| T034 | Port `MigrateDefaultsCommand` → `MigrateDefaultsHandler` + migrate test + snapshot pass | WP07 | [D] |
| T035 | Migrate `Migrate/` helper namespace if any test references CommandTester directly | WP07 | — | [D] |
| T036 | Refactor `AbstractMakeCommand` (Symfony parent) into `AbstractMakeHandler` (POPO base) | WP08 | — | [D] |
| T037 | Port `Make/MakeEntityCommand` → `MakeEntityHandler` + migrate test + snapshot pass | WP08 | [D] |
| T038 | Port `Make/MakeJobCommand` → `MakeJobHandler` + migrate test + snapshot pass | WP08 | [D] |
| T039 | Port `Make/MakeListenerCommand` → `MakeListenerHandler` + migrate test + snapshot pass | WP08 | [D] |
| T040 | Port `Make/MakeMigrationCommand` → `MakeMigrationHandler` + migrate test + snapshot pass | WP08 | [D] |
| T041 | Port `Make/MakePolicyCommand` → `MakePolicyHandler` + migrate test + snapshot pass | WP08 | [D] |
| T042 | Port `Make/MakeProviderCommand` → `MakeProviderHandler` + migrate test + snapshot pass | WP09 | [D] |
| T043 | Port `Make/MakePublicCommand` → `MakePublicHandler` + migrate integration test + snapshot pass | WP09 | [D] |
| T044 | Port `Make/MakeTestCommand` → `MakeTestHandler` + migrate test + snapshot pass | WP09 | [D] |
| T045 | Port `MakeEntityTypeCommand` → `MakeEntityTypeHandler` + migrate test + snapshot pass | WP09 | [D] |
| T046 | Port `MakePluginCommand` → `MakePluginHandler` + migrate test + snapshot pass | WP09 | [D] |
| T047 | Port `Optimize/OptimizeCommand` → `OptimizeHandler` + migrate test + snapshot pass | WP10 | [D] |
| T048 | Port `Optimize/OptimizeClearCommand` → `OptimizeClearHandler` + migrate test + snapshot pass | WP10 | [D] |
| T049 | Port `Optimize/OptimizeConfigCommand` → `OptimizeConfigHandler` + migrate test + snapshot pass | WP10 | [D] |
| T050 | Port `Optimize/OptimizeManifestCommand` → `OptimizeManifestHandler` + migrate test + snapshot pass | WP10 | [D] |
| T051 | Port `QueueWorkCommand` → `QueueWorkHandler` + migrate test + snapshot pass | WP11 | [D] |
| T052 | Port `QueueFailedCommand` → `QueueFailedHandler` + migrate test + snapshot pass | WP11 | [D] |
| T053 | Port `QueueRetryCommand` → `QueueRetryHandler` + migrate test + snapshot pass | WP11 | [D] |
| T054 | Port `QueueFlushCommand` → `QueueFlushHandler` + migrate test + snapshot pass | WP11 | [D] |
| T055 | Port `Telescope/TelescopeListCommand` → `TelescopeListHandler` + migrate test + snapshot pass | WP12 | [D] |
| T056 | Port `Telescope/TelescopeClearCommand` → `TelescopeClearHandler` + migrate test + snapshot pass | WP12 | [D] |
| T057 | Port `Telescope/TelescopePruneCommand` → `TelescopePruneHandler` + migrate test + snapshot pass | WP12 | [D] |
| T058 | Port `Telescope/TelescopeValidateCommand` → `TelescopeValidateHandler` + migrate test + snapshot pass | WP12 | [D] |
| T059 | Port `ScheduleListCommand` → `ScheduleListHandler` + migrate test + snapshot pass | WP13 | [D] |
| T060 | Port `ScheduleRunCommand` → `ScheduleRunHandler` + migrate test + snapshot pass | WP13 | [D] |
| T061 | Port `Perf/PerformanceBaselineCommand` → `PerformanceBaselineHandler` + migrate test + snapshot pass | WP13 | [D] |
| T062 | Port `Perf/PerformanceCompareCommand` → `PerformanceCompareHandler` + migrate test + snapshot pass | WP13 | [D] |
| T063 | Port `EntityCreateCommand` → `EntityCreateHandler` + migrate test + snapshot pass | WP14 | [D] |
| T064 | Port `EntityListCommand` → `EntityListHandler` + migrate test + snapshot pass | WP14 | [D] |
| T065 | Port `EntityTypeListCommand` → `EntityTypeListHandler` + migrate test + snapshot pass | WP14 | [D] |
| T066 | Port `TypeEnableCommand` → `TypeEnableHandler` + migrate test + snapshot pass | WP14 | [D] |
| T067 | Port `TypeDisableCommand` → `TypeDisableHandler` + migrate test + snapshot pass + lifecycle test fix | WP14 | [D] |
| T068 | Port `UserCreateCommand` → `UserCreateHandler` + migrate test + snapshot pass | WP15 | [D] |
| T069 | Port `UserRoleCommand` → `UserRoleHandler` + migrate test + snapshot pass | WP15 | [D] |
| T070 | Port `PermissionListCommand` → `PermissionListHandler` + migrate test + snapshot pass | WP15 | [D] |
| T071 | Port `IngestRunCommand` → `IngestRunHandler` + migrate test + snapshot pass + regression test fix | WP16 | [D] |
| T072 | Port `IngestDashboardCommand` → `IngestDashboardHandler` + migrate test + snapshot pass | WP16 | [D] |
| T073 | Port `SearchReindexCommand` → `SearchReindexHandler` + migrate test + snapshot pass | WP16 | [D] |
| T074 | Port `SemanticWarmCommand` → `SemanticWarmHandler` + migrate test + snapshot pass | WP16 | [D] |
| T075 | Port `SemanticRefreshCommand` → `SemanticRefreshHandler` + migrate test + snapshot pass | WP16 | [D] |
| T076 | Port `ConfigExportCommand` → `ConfigExportHandler` + migrate test + snapshot pass | WP17 | [P] |
| T077 | Port `ConfigImportCommand` → `ConfigImportHandler` + migrate test + snapshot pass | WP17 | [P] |
| T078 | Port `CacheClearCommand` → `CacheClearHandler` + migrate test + snapshot pass | WP17 | [P] |
| T079 | Port `DbInitCommand` → `DbInitHandler` + migrate test + snapshot pass | WP17 | [P] |
| T080 | Port `AuditLogCommand` → `AuditLogHandler` + migrate test + snapshot pass | WP17 | [P] |
| T081 | Port `BundleScaffoldCommand` → `BundleScaffoldHandler` + migrate test + snapshot pass | WP18 | [P] |
| T082 | Port `FixtureScaffoldCommand` → `FixtureScaffoldHandler` + migrate test + snapshot pass | WP18 | [P] |
| T083 | Port `FixtureGenerateCommand` → `FixtureGenerateHandler` + migrate test + snapshot pass | WP18 | [P] |
| T084 | Port `FixturePackRefreshCommand` → `FixturePackRefreshHandler` + migrate test + snapshot pass | WP18 | [P] |
| T085 | Port `RelationshipTypeScaffoldCommand` → `RelationshipTypeScaffoldHandler` + migrate test + snapshot pass | WP19 | [P] |
| T086 | Port `WorkflowScaffoldCommand` → `WorkflowScaffoldHandler` + migrate test + snapshot pass | WP19 | [P] |
| T087 | Port `ExtensionScaffoldCommand` → `ExtensionScaffoldHandler` + migrate test + snapshot pass | WP19 | [P] |
| T088 | Port `ScaffoldAuthCommand` → `ScaffoldAuthHandler` + migrate test + snapshot pass | WP19 | [P] |
| T089 | Port `AboutCommand` → `AboutHandler` + migrate test + snapshot pass | WP20 | [P] |
| T090 | Port `AdminBuildCommand` → `AdminBuildHandler` + migrate test + snapshot pass | WP20 | [P] |
| T091 | Port `AdminDevCommand` → `AdminDevHandler` + migrate test + snapshot pass | WP20 | [P] |
| T092 | Port `DebugContextCommand` → `DebugContextHandler` + migrate test + snapshot pass | WP20 | [P] |
| T093 | Port `EventListCommand` → `EventListHandler` + migrate test + snapshot pass | WP20 | [P] |
| T094 | Port `InstallCommand` → `InstallHandler` + migrate test + snapshot pass | WP21 | [P] |
| T095 | Port `RouteListCommand` → `RouteListHandler` + migrate test + snapshot pass | WP21 | [P] |
| T096 | Port `ServeCommand` → `ServeHandler` + migrate test + snapshot pass | WP21 | [P] |
| T097 | Port `SyncRulesCommand` → `SyncRulesHandler` + migrate test + snapshot pass | WP21 | [P] |
| T098 | Port `WaaseyaaVersionCommand` → `WaaseyaaVersionHandler` + migrate test + snapshot pass | WP21 | [P] |
| T099 | Port `MakePluginCommand` provenance hooks (refactor `Provenance/ComposerProvenanceReporter` to drop Symfony Console) | WP21 | — |
| T100 | Port `packages/northcloud/src/Command/NcSyncCommand` → `NcSyncHandler` + migrate test + snapshot pass | WP22 | [P] |
| T101 | Update `packages/northcloud/src/Provider/NorthCloudServiceProvider` to implement `HasNativeCommandsInterface` and stop importing Symfony Console | WP22 | — |
| T102 | Delete `packages/foundation/src/ServiceProvider/Capability/HasCommandsInterface.php` | WP23 | — |
| T103 | Delete `packages/cli/src/WaaseyaaApplication.php` and `packages/cli/src/CliCommandRegistry.php` | WP23 | [P] |
| T104 | Remove dual-boot adapter from `bin/waaseyaa`; native discovery only | WP23 | — |
| T105 | Drop `symfony/console` from `packages/cli/composer.json` runtime `require` | WP23 | — |
| T106 | Run `composer why symfony/console` and assert no waaseyaa/* runtime chain depends on it | WP23 | — |
| T107 | Author `docs/specs/cli-kernel.md` covering parser semantics, exit codes, provider contract, testing harness, layer placement | WP24 | — |
| T108 | Update `docs/specs/operator-diagnostics.md` to reference `CliKernel`/`CommandDefinition` instead of Symfony Console | WP24 | [P] |
| T109 | Extend orchestration table in `CLAUDE.md` (root and `packages/cli/CLAUDE.md` if present) with `cli-kernel.md` mapping | WP24 | [P] |
| T110 | Run `tools/drift-detector.sh` and resolve any flagged staleness | WP24 | — |
| T111 | Re-run perf harness post-cut against `list` and `health:check`; record numbers in `plan.md` § Performance Baseline | WP25 | — |
| T112 | Assert NFR-001 (≤ 110% baseline wall-time) and NFR-002 (≤ +4 MiB peak memory); fail WP if not met | WP25 | — |
| T113 | Run all snapshot integration tests; assert byte-equality vs WP01-captured fixtures for every public command | WP25 | — |
| T114 | Run gate stack: `composer cs-check`, `composer phpstan`, `bin/check-package-layers`, `bin/check-composer-policy`, `tools/drift-detector.sh`, full `phpunit` | WP25 | — |
| T115 | Delete the WP01 perf-harness script (transient artifact); confirm `kitty-specs/.../scripts/` is empty | WP25 | — |

---

## Work Packages

### WP01 — Pre-cut Baseline & Snapshot Capture

**Goal**: Establish the empirical pre-cut baseline (perf + behavioural) that every later WP measures against. Without this WP, NFR-001/002 and FR-015 cannot be verified.

**Priority**: P0 — gating. All later WPs depend on snapshots being captured first.
**Independent test**: Run `kitty-specs/.../scripts/perf-harness.sh list 10`; numbers appear in plan.md; `packages/cli/tests/Fixtures/snapshots/*.txt` exists for every command listed by `bin/waaseyaa list`.

**Subtasks**:
- [x] T001 Capture pre-cut wall-time + memory baseline (WP01)
- [x] T002 Commit perf-harness script (WP01)
- [x] T003 Capture stdout/stderr/exit-code snapshots for every shipped public command (WP01)
- [x] T004 Record numbers in `plan.md` § Performance Baseline (WP01)
- [x] T005 Verify snapshot fixture set is comprehensive (WP01)

**Implementation sketch**: Author `scripts/perf-harness.sh` per research §R-07. Author `scripts/snapshot-capture.sh` that loops over `bin/waaseyaa list` parsing the command names, runs each with `--help` and (where safe) with default args, captures stdout/stderr/exit-code into `packages/cli/tests/Fixtures/snapshots/<name>.txt`. Run harnesses, paste numbers into plan.md. **Estimated prompt**: ~280 lines.

**Parallel ops**: T002 + T003 are independent file creations.
**Dependencies**: none.
**Risk**: Snapshot capture must be deterministic — set `WAASEYAA_SNAPSHOT=1` env so commands that emit timestamps emit fixed values.

---

### WP02 — Native Runtime Core

**Goal**: Land the parser, types, and registry that everything else depends on. After this WP, the new runtime exists in source but no commands run on it yet.

**Priority**: P0 — blocks every other infra/port WP.
**Independent test**: New unit tests in `packages/cli/tests/Unit/Kernel/` and `Parser/` pass; existing Symfony-based commands still work (no regression).

**Subtasks**:
- [x] T006 `ArgumentMode` and `OptionMode` enums (WP02)
- [x] T007 `ArgumentDefinition` + invariant tests (WP02)
- [x] T008 `OptionDefinition` + invariant tests (WP02)
- [x] T009 `CommandDefinition` (handler normalisation) + invariant tests (WP02)
- [x] T010 `CommandRegistry` + tests (WP02)
- [x] T011 `ArgvParser` + `ParsedInput` + `ParseError` + edge-case test matrix (WP02)

**Implementation sketch**: Follow [`data-model.md`](./data-model.md) and [`contracts/command-definition.md`](./contracts/command-definition.md). Parser test matrix sourced from [`research.md`](./research.md) §R-02. **Estimated prompt**: ~480 lines.

**Parallel ops**: T006/T007/T008 are independent files; T009/T010/T011 are sequential within this WP because each depends on prior records.
**Dependencies**: WP01.
**Risk**: Parser semantics must round-trip every shape used by current commands. Mitigated by audit of all 74 commands plus test matrix per FR-002.

---

### WP03 — I/O, Help, Tester

**Goal**: Land `CliIO`, output writers, help renderer, and `CliTester` so handler authors and tests have a stable surface.

**Priority**: P0 — blocks port WPs (06–22).
**Independent test**: `tests/Unit/Io/`, `tests/Unit/Kernel/HelpRendererTest.php`, `tests/Unit/Testing/CliTesterTest.php` all green.

**Subtasks**:
- [x] T012 `CliInput`, `CliOutput`, `BufferedCliOutput`, `StdinSource`, `EmptyStdinSource`, `StringQueueStdinSource` (WP03)
- [x] T013 `CliIO` with non-TTY ask/confirm fallback + tests (WP03)
- [x] T014 `HelpRenderer` + golden tests (WP03)
- [x] T015 `CliTester::for/execute/executeMap/getExitCode/getStdout/getStderr/getOutput` + tests (WP03)
- [x] T016 TTY detection helper (WP03)

**Implementation sketch**: Per [`contracts/cli-io.md`](./contracts/cli-io.md) and [`contracts/cli-tester.md`](./contracts/cli-tester.md). HelpRenderer goldens snapshot-tested. **Estimated prompt**: ~420 lines.

**Parallel ops**: T012, T014, T016 independent files.
**Dependencies**: WP02.
**Risk**: HelpRenderer output must be deterministic — rely on alphabetical option sort per research §R-06.

---

### WP04 — Provider Capability + Kernel Service Provider

**Goal**: Wire the new capability interface in foundation, the manifest discovery, and the `CliKernel` itself with `CliKernelServiceProvider::buildRegistry()`.

**Priority**: P0 — bridges runtime core (WP02/03) to bootstrap (WP05).
**Independent test**: Integration test — register a fake provider implementing `HasNativeCommandsInterface`, confirm its commands appear in the registry and dispatch through `CliKernel`.

**Subtasks**:
- [x] T017 Add `HasNativeCommandsInterface` to foundation (WP04)
- [x] T018 Contract test asserting Symfony-import-free interface (WP04)
- [x] T019 Extend `PackageManifestCompiler` capability scan (WP04)
- [x] T020 Implement `CliKernelServiceProvider::buildRegistry()` (WP04)
- [x] T021 Implement `CliKernel::run(argv): int` + integration tests (WP04)

**Implementation sketch**: Per [`contracts/has-native-commands.md`](./contracts/has-native-commands.md) and [`contracts/cli-kernel.md`](./contracts/cli-kernel.md). **Estimated prompt**: ~380 lines.

**Parallel ops**: T017 + T018 share a file; T019/T020/T021 are sequential.
**Dependencies**: WP02, WP03.
**Risk**: `PackageManifestCompiler` is layer-0 — must NOT import Layer 6 types. Use string FQN lookup per the existing pattern documented in CLAUDE.md.

---

### WP05 — Entry-point + Dual-Boot Adapter

**Goal**: Rewire `bin/waaseyaa` onto `CliApplication`. Introduce a temporary dual-boot adapter that reads BOTH `HasCommandsInterface` (legacy Symfony commands) and `HasNativeCommandsInterface` (new) so the tree stays green between WPs 06–22. The adapter is deleted in WP23.

**Priority**: P0 — gates all port WPs (no port-WP can land before this WP makes the new kernel runnable).
**Independent test**: `bin/waaseyaa list` lists every command exactly as before, including a new test command registered via `HasNativeCommandsInterface`.

**Subtasks**:
- [x] T022 Implement `CliApplication::main()` (WP05)
- [x] T023 Rewrite `bin/waaseyaa` to boot `CliApplication` with dual-boot discovery (WP05)
- [x] T024 Implement `LegacySymfonyCommandAdapter` wrapping Symfony `Command` into a `CommandDefinition` (WP05)
- [x] T025 Integration test: dual-boot serves both legacy + native end-to-end (WP05)

**Implementation sketch**: `LegacySymfonyCommandAdapter` introspects a Symfony `Command` via reflection, builds an equivalent `CommandDefinition`, and delegates execution via a closure that converts `CliIO` back into Symfony `InputInterface`/`OutputInterface` adapter shims. Lives in `packages/cli/src/Compat/` and is deleted with extreme prejudice in WP23. **Estimated prompt**: ~440 lines.

**Parallel ops**: T022 independent of T023; T024/T025 sequential.
**Dependencies**: WP04.
**Risk**: Dual-boot adapter must not become permanent. WP23 explicitly deletes the entire `packages/cli/src/Compat/` directory; WP25 grep gates it.

---

### WP06 — Port: Health & Schema (FR-015 critical surface)

**Goal**: Port the four operator-diagnostics commands first because they are the strictest contract surface in the spec (FR-015) and exercise the JSON-envelope `do_not_change` rule from the bulk-edit map.

**Priority**: P1 — first port WP after infra; demonstrates the port pattern.
**Independent test**: All four handlers' tests + snapshot integration tests pass; `bin/waaseyaa health:check --json` byte-equals WP01 fixture.

**Subtasks**:
- [x] T026 Port `HealthCheckCommand` → `HealthCheckHandler` (WP06)
- [x] T027 Port `HealthReportCommand` → `HealthReportHandler` (WP06)
- [x] T028 Port `SchemaCheckCommand` → `SchemaCheckHandler` (WP06)
- [x] T029 Port `SchemaListCommand` → `SchemaListHandler` (WP06)
- [x] T030 Update `packages/cli` `CliServiceProvider` to register the four handlers via `HasNativeCommandsInterface` (WP06)

**Implementation sketch**: Follow the canonical port pattern (extracted into `quickstart.md`). Each command's `configure()` becomes a `CommandDefinition` literal in the provider; `execute(InputInterface, OutputInterface): int` becomes `execute(CliIO): int` on the handler class. **Estimated prompt**: ~340 lines.

**Parallel ops**: T026–T029 are independent files.
**Dependencies**: WP05.
**Risk**: JSON envelope keys frozen — snapshot test asserts byte-equality.

---

### WP07 — Port: Migrate group

**Goal**: Port the migration commands. The `Migrate/` helper namespace classes are infra utilities (DryRunPlanner, VerifyRunner, etc.) — they don't extend Symfony but their tests may use `CommandTester`.

**Priority**: P1.
**Independent test**: `bin/waaseyaa migrate:status` byte-equals WP01 fixture; helper-class tests green.

**Subtasks**:
- [x] T031 Port `MigrateCommand` → `MigrateHandler` (WP07)
- [x] T032 Port `MigrateRollbackCommand` → `MigrateRollbackHandler` (WP07)
- [x] T033 Port `MigrateStatusCommand` → `MigrateStatusHandler` (WP07)
- [x] T034 Port `MigrateDefaultsCommand` → `MigrateDefaultsHandler` (WP07)
- [x] T035 Migrate any `Migrate/` helper test that uses `CommandTester` to `CliTester` (WP07)

**Estimated prompt**: ~360 lines.
**Parallel ops**: T031–T034.
**Dependencies**: WP05.
**Risk**: `MakeMigrationCommand` requires `$projectRoot` constructor param (CLAUDE.md gotcha) — preserve.

---

### WP08 — Port: Make group A (Entity, Job, Listener, Migration, Policy)

**Subtasks**:
- [x] T036 Refactor `AbstractMakeCommand` → `AbstractMakeHandler` POPO base (WP08)
- [x] T037 Port `MakeEntityCommand` → `MakeEntityHandler` (WP08)
- [x] T038 Port `MakeJobCommand` → `MakeJobHandler` (WP08)
- [x] T039 Port `MakeListenerCommand` → `MakeListenerHandler` (WP08)
- [x] T040 Port `MakeMigrationCommand` → `MakeMigrationHandler` (WP08)
- [x] T041 Port `MakePolicyCommand` → `MakePolicyHandler` (WP08)

**Estimated prompt**: ~380 lines. **Dependencies**: WP05.

---

### WP09 — Port: Make group B (Provider, Public, Test, EntityType, Plugin)

**Subtasks**:
- [x] T042 Port `MakeProviderCommand` → `MakeProviderHandler` (WP09)
- [x] T043 Port `MakePublicCommand` → `MakePublicHandler` (WP09)
- [x] T044 Port `MakeTestCommand` → `MakeTestHandler` (WP09)
- [x] T045 Port `MakeEntityTypeCommand` → `MakeEntityTypeHandler` (WP09)
- [x] T046 Port `MakePluginCommand` → `MakePluginHandler` (WP09)

**Estimated prompt**: ~340 lines. **Dependencies**: WP08 (uses AbstractMakeHandler).

---

### WP10 — Port: Optimize group

**Subtasks**:
- [x] T047 `Optimize/OptimizeCommand` → `OptimizeHandler` (WP10)
- [x] T048 `OptimizeClearCommand` → `OptimizeClearHandler` (WP10)
- [x] T049 `OptimizeConfigCommand` → `OptimizeConfigHandler` (WP10)
- [x] T050 `OptimizeManifestCommand` → `OptimizeManifestHandler` (WP10)

**Estimated prompt**: ~280 lines. **Dependencies**: WP05.

---

### WP11 — Port: Queue group

**Subtasks**:
- [x] T051 `QueueWorkCommand` → `QueueWorkHandler` (WP11)
- [x] T052 `QueueFailedCommand` → `QueueFailedHandler` (WP11)
- [x] T053 `QueueRetryCommand` → `QueueRetryHandler` (WP11)
- [x] T054 `QueueFlushCommand` → `QueueFlushHandler` (WP11)

**Estimated prompt**: ~280 lines. **Dependencies**: WP05.
**Risk**: `Worker::run` baseline-memory check from #1397 is in `packages/queue/`, untouched here. The CLI handler just dispatches to it.

---

### WP12 — Port: Telescope group

**Subtasks**:
- [x] T055 `TelescopeListCommand` → `TelescopeListHandler` (WP12)
- [x] T056 `TelescopeClearCommand` → `TelescopeClearHandler` (WP12)
- [x] T057 `TelescopePruneCommand` → `TelescopePruneHandler` (WP12)
- [x] T058 `TelescopeValidateCommand` → `TelescopeValidateHandler` (WP12)

**Estimated prompt**: ~280 lines. **Dependencies**: WP05.

---

### WP13 — Port: Schedule + Perf

**Subtasks**:
- [x] T059 `ScheduleListCommand` → `ScheduleListHandler` (WP13)
- [x] T060 `ScheduleRunCommand` → `ScheduleRunHandler` (WP13)
- [x] T061 `Perf/PerformanceBaselineCommand` → `PerformanceBaselineHandler` (WP13)
- [x] T062 `Perf/PerformanceCompareCommand` → `PerformanceCompareHandler` (WP13)

**Estimated prompt**: ~280 lines. **Dependencies**: WP05.

---

### WP14 — Port: Entity + Type lifecycle

**Subtasks**:
- [x] T063 `EntityCreateCommand` → `EntityCreateHandler` (WP14)
- [x] T064 `EntityListCommand` → `EntityListHandler` (WP14)
- [x] T065 `EntityTypeListCommand` → `EntityTypeListHandler` (WP14)
- [x] T066 `TypeEnableCommand` → `TypeEnableHandler` (WP14)
- [x] T067 `TypeDisableCommand` → `TypeDisableHandler` + lifecycle test fix (WP14)

**Estimated prompt**: ~340 lines. **Dependencies**: WP05.
**Risk**: `tests/Unit/Command/TypeLifecycleCommandTest.php` exercises both Enable+Disable; migrate together.

---

### WP15 — Port: User + Permission

**Subtasks**:
- [x] T068 `UserCreateCommand` → `UserCreateHandler` (WP15)
- [x] T069 `UserRoleCommand` → `UserRoleHandler` (WP15)
- [x] T070 `PermissionListCommand` → `PermissionListHandler` (WP15)

**Estimated prompt**: ~240 lines. **Dependencies**: WP05.

---

### WP16 — Port: Ingest + Search + Semantic

**Subtasks**:
- [x] T071 `IngestRunCommand` → `IngestRunHandler` + regression test fix (WP16)
- [x] T072 `IngestDashboardCommand` → `IngestDashboardHandler` (WP16)
- [x] T073 `SearchReindexCommand` → `SearchReindexHandler` (WP16)
- [x] T074 `SemanticWarmCommand` → `SemanticWarmHandler` (WP16)
- [x] T075 `SemanticRefreshCommand` → `SemanticRefreshHandler` (WP16)

**Estimated prompt**: ~340 lines. **Dependencies**: WP05.
**Risk**: `IngestionFixturePackRegressionTest.php` may need targeted updates.

---

### WP17 — Port: Config + Cache + Db + Audit

**Subtasks**:
- [ ] T076 `ConfigExportCommand` → `ConfigExportHandler` (WP17)
- [ ] T077 `ConfigImportCommand` → `ConfigImportHandler` (WP17)
- [ ] T078 `CacheClearCommand` → `CacheClearHandler` (WP17)
- [ ] T079 `DbInitCommand` → `DbInitHandler` (WP17)
- [ ] T080 `AuditLogCommand` → `AuditLogHandler` (WP17)

**Estimated prompt**: ~340 lines. **Dependencies**: WP05.

---

### WP18 — Port: Bundle + Fixture scaffolds

**Subtasks**:
- [ ] T081 `BundleScaffoldCommand` → `BundleScaffoldHandler` (WP18)
- [ ] T082 `FixtureScaffoldCommand` → `FixtureScaffoldHandler` (WP18)
- [ ] T083 `FixtureGenerateCommand` → `FixtureGenerateHandler` (WP18)
- [ ] T084 `FixturePackRefreshCommand` → `FixturePackRefreshHandler` (WP18)

**Estimated prompt**: ~280 lines. **Dependencies**: WP05.

---

### WP19 — Port: Other scaffolds (Relationship/Workflow/Extension/Auth)

**Subtasks**:
- [ ] T085 `RelationshipTypeScaffoldCommand` → `RelationshipTypeScaffoldHandler` (WP19)
- [ ] T086 `WorkflowScaffoldCommand` → `WorkflowScaffoldHandler` (WP19)
- [ ] T087 `ExtensionScaffoldCommand` → `ExtensionScaffoldHandler` (WP19)
- [ ] T088 `ScaffoldAuthCommand` → `ScaffoldAuthHandler` (WP19)

**Estimated prompt**: ~280 lines. **Dependencies**: WP05.

---

### WP20 — Port: Misc cluster A (About/Admin/Debug/Event)

**Subtasks**:
- [ ] T089 `AboutCommand` → `AboutHandler` (WP20)
- [ ] T090 `AdminBuildCommand` → `AdminBuildHandler` (WP20)
- [ ] T091 `AdminDevCommand` → `AdminDevHandler` (WP20)
- [ ] T092 `DebugContextCommand` → `DebugContextHandler` (WP20)
- [ ] T093 `EventListCommand` → `EventListHandler` (WP20)

**Estimated prompt**: ~340 lines. **Dependencies**: WP05.

---

### WP21 — Port: Misc cluster B (Install/Route/Serve/Sync/Version) + Provenance

**Subtasks**:
- [ ] T094 `InstallCommand` → `InstallHandler` (WP21)
- [ ] T095 `RouteListCommand` → `RouteListHandler` (WP21)
- [ ] T096 `ServeCommand` → `ServeHandler` (WP21)
- [ ] T097 `SyncRulesCommand` → `SyncRulesHandler` (WP21)
- [ ] T098 `WaaseyaaVersionCommand` → `WaaseyaaVersionHandler` (WP21)
- [ ] T099 Refactor `Provenance/ComposerProvenanceReporter` to drop Symfony Console (WP21)

**Estimated prompt**: ~400 lines. **Dependencies**: WP05.

---

### WP22 — Port: Northcloud `nc:sync`

**Subtasks**:
- [ ] T100 Port `packages/northcloud/src/Command/NcSyncCommand` → `NcSyncHandler` (WP22)
- [ ] T101 Update `NorthCloudServiceProvider` to implement `HasNativeCommandsInterface` (WP22)

**Estimated prompt**: ~220 lines. **Dependencies**: WP05.

---

### WP23 — Hard-cut: remove legacy + drop dep

**Goal**: Make the cut. Remove the old interface, the Symfony Application subclass, the legacy registry, the dual-boot adapter, and the runtime composer require. After this WP, `composer why symfony/console` shows no first-party runtime chain.

**Subtasks**:
- [ ] T102 Delete `HasCommandsInterface.php` (WP23)
- [ ] T103 Delete `WaaseyaaApplication.php` and `CliCommandRegistry.php` (WP23)
- [ ] T104 Remove dual-boot adapter from `bin/waaseyaa` and the `packages/cli/src/Compat/` directory (WP23)
- [ ] T105 Drop `symfony/console` from `packages/cli/composer.json` runtime `require` (WP23)
- [ ] T106 Run `composer why symfony/console`; assert no waaseyaa/* runtime chain (WP23)

**Estimated prompt**: ~260 lines. **Dependencies**: WP06, WP07, WP08, WP09, WP10, WP11, WP12, WP13, WP14, WP15, WP16, WP17, WP18, WP19, WP20, WP21, WP22 (all port WPs must be merged before the cut).

---

### WP24 — Spec authoring & cross-refs

**Subtasks**:
- [ ] T107 Author `docs/specs/cli-kernel.md` (WP24)
- [ ] T108 Update `docs/specs/operator-diagnostics.md` (WP24)
- [ ] T109 Extend orchestration table in `CLAUDE.md` (WP24)
- [ ] T110 Run `tools/drift-detector.sh`; resolve any flagged staleness (WP24)

**Estimated prompt**: ~300 lines. **Dependencies**: WP23.

---

### WP25 — Final perf + parity verification

**Goal**: Prove the cut delivered. Re-run the WP01 perf harness, assert NFR thresholds, run all snapshot tests, run the gate stack, delete transient artefacts.

**Subtasks**:
- [ ] T111 Re-run perf harness post-cut; record numbers in plan.md (WP25)
- [ ] T112 Assert NFR-001 + NFR-002 (WP25)
- [ ] T113 Run all snapshot integration tests; assert byte-equality (WP25)
- [ ] T114 Run gate stack (`cs-check`, `phpstan`, layer + composer + drift, full phpunit) (WP25)
- [ ] T115 Delete WP01 perf-harness script (WP25)

**Estimated prompt**: ~280 lines. **Dependencies**: WP24.

---

## Parallelization

Once **WP05** lands, every port WP (WP06–WP22) is independent (no shared `owned_files`). They can be dispatched on parallel lanes. WP08 must precede WP09 (`AbstractMakeHandler` shared base). WP23/24/25 are strictly sequential.

Up to **17 parallel lanes** (one per port WP) can run simultaneously after WP05.

## MVP scope

Spec Kitty's MVP-after-foundation here is **WP01–WP06**: snapshot baseline + native runtime + entry-point dual-boot + first port (Health/Schema). At that point operators see no behavioural change but the kernel is provably running on the native runtime for those four commands. That milestone is not landed alone — every port WP + WP23 must merge together to honour the hard-cut constraint (C-004) — but it is the cleanest pause-point for review.

## Estimated overall prompt size

25 WPs × ~310 line average = ~7800 lines of WP guidance. Within target band (200–500 per WP); only WP02 and WP05 push the upper bound, both justified by their cross-cutting infra nature.
