---
work_package_id: WP06
title: 'CLI runner: import:run + run-all + status + dry-run'
dependencies:
- WP01
- WP02
- WP05
requirement_refs:
- FR-032
- FR-033
- FR-034
- FR-039
- FR-040
- FR-045
- FR-046
- FR-047
- FR-048
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T033
- T034
- T035
- T036
- T037
- T038
agent: "claude:opus:waaseyaa-implementer:implementer"
shell_pid: "662802"
history:
- timestamp: '2026-05-13T02:27:32Z'
  actor: spec-kitty.tasks
  event: wp_created
  notes: Generated as part of M-002 task materialization.
authoritative_surface: packages/migration/src/Runner/
execution_mode: code_change
mission_id: 01KRCDE9ZXK2JEFPT6THSBVKNY
mission_slug: migration-platform-v1-01KRCDE9
owned_files:
- packages/migration/src/Runner/MigrationRunner.php
- packages/migration/src/Runner/RunOptions.php
- packages/migration/src/Runner/RunReport.php
- packages/migration/src/Runner/ProcessChainExecutor.php
- packages/migration/src/Exception/MigrationAbortedException.php
- packages/cli/src/Command/Import/ImportRunCommand.php
- packages/cli/src/Command/Import/ImportRunAllCommand.php
- packages/cli/src/Command/Import/ImportStatusCommand.php
- packages/migration/tests/Unit/Runner/MigrationRunnerTest.php
- packages/migration/tests/Unit/Runner/ProcessChainExecutorTest.php
- packages/cli/tests/Unit/Command/Import/ImportRunCommandTest.php
- packages/cli/tests/Unit/Command/Import/ImportRunAllCommandTest.php
- packages/cli/tests/Unit/Command/Import/ImportStatusCommandTest.php
priority: p1
tags:
- stable-surface
- layer-6
- cli
- layer-3
- runner
---

# WP06 — CLI runner: import:run + run-all + status + dry-run

## Objective

Compose the four pieces shipped so far (registry, plugins, id-map, EntityDestination) into a working runner and expose it through three CLI commands: `import:run`, `import:run-all`, `import:status`. Plus `--dry-run` and `--limit=N` flags per FR-039 / FR-040.

This WP touches two packages: `packages/migration/` (the `MigrationRunner` core) and `packages/cli/` (the three command classes). Both are owned by this WP — no overlap with earlier WPs.

## Dependencies

- Internal: WP01 (plugin interfaces, registry), WP02 (`MigrationRegistry`, `DependencyGraph`), WP05 (`EntityDestination`).
- External: None. Uses `packages/cli`'s existing command base (verify by reading `packages/cli/src/CliKernel.php` and `packages/cli/src/CommandDefinition.php` at implementation time — those are the L6 entry points).
- Charter anchors: §5.8 (proposed) — CLI commands; existing CLI Kernel surface untouched.

## Scope (in / out)

**In scope**
- `MigrationRunner` — the procedural orchestrator that walks a single migration end to end (FR-032 logic body).
- `RunOptions` — value object capturing `--dry-run`, `--halt-on-error`, `--limit=N` (FR-039, FR-040, FR-047).
- `RunReport` — value object summarizing the run's outcome (counts, errors, run id).
- `ProcessChainExecutor` — composes a process-plugin chain for one destination field (the FR-010 chain semantics live here, not in the interface).
- `MigrationAbortedException` — typed exception raised when the runner halts on error (FR-045 continued, FR-047 / FR-048 path).
- Three CLI commands under `packages/cli/src/Command/Import/` per spec §9 and `contracts/cli-runner.md`:
  - `ImportRunCommand` — `bin/waaseyaa import:run <migration-id> [--dry-run] [--halt-on-error] [--limit=N]` (FR-032).
  - `ImportRunAllCommand` — `bin/waaseyaa import:run-all` walks the DAG in topological order (FR-033).
  - `ImportStatusCommand` — `bin/waaseyaa import:status [<migration-id>]` (FR-034).
- Status state-machine: `pending` → `running` → `complete`/`partial`/`failed` — computed from `migration_id_map` row count + (later) `migration_run_state` data. WP07 fills in the resume / partial logic; this WP ships the placeholder reading id-map counts only.

**Out of scope**
- Resume (`import:resume`) — WP07.
- Rollback / reset (`import:rollback`, `import:reset`) — WP08.
- Concurrency lock — WP09.
- The `migration_run_state` schema and per-record progress — WP07. This WP's `MigrationRunner` writes a tiny in-memory progress structure into `RunReport`; persistence lands in WP07.

## Branch strategy

Planning/base branch: `main`. Merge target: `main`. Per-lane worktree. Run `spec-kitty agent action implement WP06 --agent opus`.

## Implementation guidance

### Subtask T033 — `RunOptions` + `RunReport` value objects

**Purpose**: Carve a clean boundary between the CLI surface and the runner core. Commands assemble `RunOptions`; the runner returns `RunReport`.

**FRs covered**: FR-039, FR-040, FR-047.

**Files**:
- `packages/migration/src/Runner/RunOptions.php` (new, ~80 lines).
- `packages/migration/src/Runner/RunReport.php` (new, ~110 lines).

**Steps**:
1. `RunOptions` (`final readonly class`, `@api`):
   ```php
   public function __construct(
       public bool $dryRun = false,
       public bool $haltOnError = false,
       public ?int $limit = null,
       public ?string $runId = null,                       // UUIDv7; auto-generated if null
   ) {}
   ```
   Validator: `$limit === null || $limit > 0`. `$runId === null || matches UUIDv7 regex`.
2. `RunReport`:
   ```php
   public function __construct(
       public string $migrationId,
       public string $runId,
       public int $total,           // -1 if source returned null count
       public int $imported,
       public int $skipped,
       public int $failed,
       public list<RecordError> $errors,                   // per-record errors (capped at 100 for memory)
       public \DateTimeImmutable $startedAt,
       public \DateTimeImmutable $finishedAt,
       public bool $aborted,                               // true if MigrationAbortedException raised
   ) {}
   ```
   With a nested `RecordError` value object: `string $sourceIdHash`, `string $code`, `string $message`, `string $stage` (`'source'|'process'|'destination'`).
3. Add `RunReport::summaryLine(): string` returning a CLI-friendly one-liner (e.g. `"wp_users_to_accounts: complete (1500/1500, 0 failed, 0 skipped)"`).

**Validation**:
- [ ] Validators reject negative `$limit`.
- [ ] `summaryLine()` matches the format documented above.

### Subtask T034 — `ProcessChainExecutor`

**Purpose**: Execute the FR-010 chain semantics — multiple processors composed in array order for one destination field.

**FRs covered**: FR-010.

**Files**:
- `packages/migration/src/Runner/ProcessChainExecutor.php` (new, ~140 lines).

**Steps**:
1. `final class ProcessChainExecutor` (internal — no `@api`, this is a runtime collaborator, not stable surface).
2. Constructor: `__construct(PluginRegistry $pluginRegistry)`.
3. `executeField(MigrationDefinition $definition, string $destinationField, SourceRecord $record, \Closure $lookup): mixed`:
   1. Read the process spec via `$definition->processForField($destinationField)`.
   2. For each chain step:
      - String → wrap as `PassThroughProcessor($string)`.
      - `ProcessPluginInterface` → use directly.
   3. Build a `ProcessContext` with `$record`, `$definition->id`, `$destinationField`, the injected `$lookup` closure.
   4. Pipe value through chain: `$value = null; foreach ($chain as $processor) { $value = $processor->transform($value, $context); }`.
   5. Return the final value.
4. Errors: any `\Throwable` from a `transform()` call wraps as `ProcessException` (FR-045) carrying the stage, plugin id, field name. Re-throw — the runner decides whether to halt or continue.

**Validation**:
- [ ] Unit test: a chain of three processors threads value correctly.
- [ ] Unit test: a string shorthand resolves to `PassThroughProcessor`.
- [ ] Unit test: a processor throwing wraps as `ProcessException`.

### Subtask T035 — `MigrationRunner` + `MigrationAbortedException`

**Purpose**: The procedural orchestrator. Walks a single migration end to end.

**FRs covered**: FR-032 (single run), FR-039 (dry-run), FR-040 (limit), FR-046 (per-record error capture), FR-047 (halt-on-error), FR-048 (run-level errors halt regardless).

**Files**:
- `packages/migration/src/Runner/MigrationRunner.php` (new, ~340 lines).
- `packages/migration/src/Exception/MigrationAbortedException.php` (new, ~50 lines).

**Steps**:
1. `final class MigrationRunner` (`@api`).
2. Constructor: `__construct(MigrationRegistry $registry, ProcessChainExecutor $chain, MigrationIdMap $idMap, LoggerInterface $logger, \Closure $clock = null)`. The clock closure returns `\DateTimeImmutable` — injectable for tests.
3. `run(string $migrationId, RunOptions $options): RunReport`:
   1. Resolve `MigrationDefinition` from `$registry->get($migrationId)`. Missing → `\OutOfBoundsException` (caller responsibility — programmer error).
   2. `$runId = $options->runId ?? UuidV7::generate()->toRfc4122()` (use Symfony Uid).
   3. `$started = ($this->clock)()`.
   4. `$source = $definition->source`.
   5. `$total = $source->count() ?? -1`.
   6. `$counters = ['imported' => 0, 'skipped' => 0, 'failed' => 0]`. `$errors = []`.
   7. Build `$lookup = $this->buildLookupClosure()` — a closure that consults `MigrationIdMap::lookupDestination()` for any `(migrationId, SourceId)` pair. Process plugins (`LookupProcessor`) use this.
   8. Iterate `$source->records()`:
      - `$processed = 0` outside the loop.
      - For each `SourceRecord`:
        - If `$options->limit !== null && $processed >= $options->limit` → break.
        - `$processed++`.
        - Try {
          - `$sourceId = $source->sourceIdFor($record)`.
          - For each destination field in `$definition->process`: `$values[$field] = $this->chain->executeField($definition, $field, $record, $lookup)`.
          - Build `$destinationRecord = new DestinationRecord($migrationId, $sourceId, $values, bundle: null, langcode: null)`.
          - If `$options->dryRun` → skip `write()` entirely. Count as 'skipped'.
          - Else → `$writeResult = $definition->destination->write($destinationRecord)`. If the id-map lookup hit returned the same `WriteResult` (idempotent skip per FR-031), count as 'skipped'; otherwise count as 'imported'.
        } catch (`ProcessException` | `DestinationWriteException` | `SourceReadException` $e) {
          - Record in `$errors` (cap at 100). `$counters['failed']++`. Log on `migration.runner` channel.
          - If `$options->haltOnError` → throw `MigrationAbortedException` carrying the `RunReport` and the underlying exception. (FR-047).
        } catch (`\Throwable` $e) {
          - Run-level error per FR-048. Always halt. Log at error. Throw `MigrationAbortedException` wrapping the underlying. (Do NOT continue: the runner cannot recover from framework-level failures like source plugin crash mid-iteration.)
        }
   9. Construct + return `RunReport(...)`.
4. `MigrationAbortedException`: `public readonly RunReport $report`, `public readonly string $code = 'MIGRATION_ABORTED'`, `public readonly ?\Throwable $previous`. Extends `\RuntimeException`. `@api`.

**Validation**:
- [ ] Unit test: a 10-record source runs to completion with all imported.
- [ ] Unit test: dry-run produces 10 skipped, 0 imported, 0 failed.
- [ ] Unit test: `--limit=5` produces 5 records processed.
- [ ] Unit test: a process error with `haltOnError=true` raises `MigrationAbortedException` after processing the failing record.
- [ ] Unit test: a process error with `haltOnError=false` continues; the run finishes with `failed > 0`.
- [ ] Unit test: a source plugin that throws mid-iteration halts (FR-048).

**Edge cases**:
- Generators that throw must be caught — wrap iteration in `try` inside the foreach as well as around the per-record body.
- The error-cap (`100`) prevents memory growth on million-error runs; document.

### Subtask T036 — `ImportRunCommand` (`bin/waaseyaa import:run`)

**Purpose**: CLI front-end for `MigrationRunner::run()`.

**FRs covered**: FR-032, FR-039, FR-040, FR-047.

**Files**:
- `packages/cli/src/Command/Import/ImportRunCommand.php` (new, ~140 lines).
- `packages/cli/tests/Unit/Command/Import/ImportRunCommandTest.php` (new).

**Steps**:
1. Extend the existing CLI command base (verify path: `packages/cli/src/Command/` — likely `AbstractCommand` or similar; resolve at implementation time).
2. Command name: `import:run`. Description: `"Run a single migration end-to-end."`.
3. Arguments:
   - `migration-id` (required, string).
4. Options:
   - `--dry-run` (bool flag).
   - `--halt-on-error` (bool flag).
   - `--limit` (int, default null).
5. `execute(InputInterface $input, OutputInterface $output): int`:
   - Build `RunOptions` from the input.
   - Resolve `MigrationRunner` from the container.
   - Call `$runner->run($migrationId, $options)`.
   - Print `$report->summaryLine()` to stdout.
   - If there were per-record errors, print a table (`migration_id | source_id_hash | stage | code | message`) capped at 20 rows; document with a footer that more errors are in `migration_run_state` (WP07).
   - Exit code: 0 on full success; 1 if `$report->failed > 0` or `$report->aborted`.
6. Catch `MigrationAbortedException` → print the report's summary + the underlying error → exit 1.

**Validation**:
- [ ] `CommandTester` covers: success path, dry-run path, limit path, halt-on-error path, abort path.
- [ ] Exit codes match the FR-032 contract.

**Edge cases**:
- An unknown `migration-id` raises `\OutOfBoundsException` (programmer error per WP02) — the command catches and prints a useful error message, exit 1. Document.

### Subtask T037 — `ImportRunAllCommand` (`bin/waaseyaa import:run-all`)

**Purpose**: Walk the DAG in dependency order.

**FRs covered**: FR-033.

**Files**:
- `packages/cli/src/Command/Import/ImportRunAllCommand.php` (new, ~130 lines).
- `packages/cli/tests/Unit/Command/Import/ImportRunAllCommandTest.php` (new).

**Steps**:
1. Command name: `import:run-all`. No required arguments.
2. Options: same flags as `ImportRunCommand` (apply to every migration walked).
3. `execute()`:
   - `$order = $registry->topologicallySorted()`.
   - For each migration, call `$runner->run($id, $options)`. Print each migration's `summaryLine()` as it completes.
   - Continue past per-migration failures unless `$options->haltOnError` is set. Per spec §9 + `contracts/cli-runner.md`, `--halt-on-error` halts after the first migration's first record-level error.
   - Aggregate: print a final summary line: `"Run-all: N migrations, M imported, K skipped, F failed (across all migrations)"`.
   - Exit code: 0 if every migration completed with `failed == 0`; 1 otherwise.

**Validation**:
- [ ] `CommandTester`: walks three migrations in order; halts on per-migration abort when flag set; continues otherwise.

### Subtask T038 — `ImportStatusCommand` (`bin/waaseyaa import:status`)

**Purpose**: Report per-migration state.

**FRs covered**: FR-034.

**Files**:
- `packages/cli/src/Command/Import/ImportStatusCommand.php` (new, ~150 lines).
- `packages/cli/tests/Unit/Command/Import/ImportStatusCommandTest.php` (new).

**Steps**:
1. Command name: `import:status`. Optional argument: `migration-id` (filter).
2. State computation (WP06 placeholder, refined by WP07):
   - For each migration in the registry:
     - `$totalSource = $definition->source->count() ?? null` (best-effort; some sources don't precompute).
     - `$importedCount = $idMap->countForMigration($migrationId)`.
     - State: `'pending'` if `$importedCount === 0`; `'complete'` if `$totalSource !== null && $importedCount >= $totalSource`; `'partial'` otherwise.
3. Output: a fixed-width table matching spec §9.2:
   ```
   ID                              STATE       TOTAL  IMPORTED  FAILED  SKIPPED  LAST RUN
   wp_users_to_accounts            complete    1500   1500      0       0        2026-05-11 14:22
   wp_posts_to_teachings           partial     5000   3217      0       0        2026-05-11 14:35
   ```
   `LAST RUN` reads from id-map's max `last_imported_at` (or `'-'` if no rows).
4. Exit code: 0 always (status is informational).

**Validation**:
- [ ] `CommandTester` against a fixture with three migrations in three states.
- [ ] When `migration-id` is supplied, only that row prints.

**Edge cases**:
- Failed counts are 0 until WP07 ships the `migration_run_state` table. Document this in code comment with a `// TODO(WP07)` placeholder so the FR-034 fields appear in the table now and connect to real data later.
- Skipped counts likewise — 0 until WP07.

## Tests

- **Unit**: T033 / T034 / T035 / T036 / T037 / T038 — one or two unit tests per class.
- **Integration**: defer to WP11 — end-to-end CSV → entity run is the integration test.
- **Conformance**: WP10.

## Definition of Done

- [ ] All six subtasks complete.
- [ ] All nine FRs cited in code as `@spec FR-xxx`.
- [ ] `composer phpstan` clean for `packages/migration/` AND `packages/cli/`.
- [ ] `composer cs-check` clean (run twice).
- [ ] `bin/check-package-layers` clean. **Especially confirm `packages/migration/` still imports only from Layer 0/1, and `packages/cli/` imports `packages/migration/` (Layer 6 → Layer 3 — allowed).**
- [ ] `bin/audit-dead-code` clean.
- [ ] `./vendor/bin/phpunit` full suite green.
- [ ] All public symbols carry `@api`; internal-only collaborators (`ProcessChainExecutor`) omit it.
- [ ] No `psr/log` imports.
- [ ] Exit codes match `contracts/cli-runner.md`.
- [ ] `import:run`, `import:run-all`, `import:status` listed by `bin/waaseyaa list` (verify by hand-running).

## Risks

- **R1 — CLI base class drift**: the `packages/cli` framework may rename `AbstractCommand` between WP06 planning and execution. Mitigation: T036 step 1 reads the live CLI surface at implementation time.
- **R2 — Generator + try/catch interaction**: an exception thrown by a generator's `current()` short-circuits iteration. The runner's `try` must wrap the entire `foreach`, not just the body. Covered by T035 unit test for source-mid-iteration crash.
- **R3 — UUIDv7 dependency**: Symfony Uid (`symfony/uid`) must be in vendor. Verify in root `composer.json` at implementation time; if absent, add it (low-risk dep).
- **R4 — Per-record error cap at 100 truncates audit trail**: documented behavior; the full per-record errors live in `migration_run_state` (WP07).
- **R5 — Mid-run process plugin lazy memory leak**: `ProcessChainExecutor` builds a fresh `ProcessContext` per record. Confirm no static caching of contexts.

## Reviewer guidance

- Check: `MigrationRunner` uses constructor-injected collaborators; no service locator.
- Check: the per-record `try/catch` catches per-record error types (Process/DestinationWrite/SourceRead) for FR-046 capture, and a separate outer `try/catch` catches `\Throwable` for FR-048 abort.
- Check: `RunOptions` and `RunReport` are `final readonly class`.
- Check: `MigrationAbortedException` carries the `RunReport` so callers can render a useful summary.
- Check: `ImportStatusCommand` does NOT hit `migration_run_state` yet — WP07 wires that in.
- Verify: CLI commands print to `OutputInterface`, not raw `echo`/`print`.
- Verify: exit codes 0 / 1 align with spec §9.1.
- Confirm: source plugin generators that throw mid-iteration trigger the FR-048 abort path.

## Activity Log

- 2026-05-13T04:36:25Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=662802 – Started implementation via action command
- 2026-05-13T04:57:47Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=662802 – Ready for review — CLI runner core (MigrationRunner + ProcessChainExecutor + RunOptions/RunReport + MigrationAbortedException) + three import:* commands wired via ImportServiceProvider; EntityDestination gained withRunId() per WP05 forward; MigrationIdMap gained maxLastImportedAt() for status. 284 migration+CLI tests + 8144 full suite green; cs-check/phpstan/layers/composer-policy all clean. Verified bin/waaseyaa import:run --help renders and import:status executes.
