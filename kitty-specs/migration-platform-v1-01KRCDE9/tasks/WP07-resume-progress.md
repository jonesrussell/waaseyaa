---
work_package_id: WP07
title: Resume + progress tracking
dependencies:
- WP04
- WP06
requirement_refs:
- FR-037
- FR-038
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T039
- T040
- T041
- T042
- T043
agent: "claude:opus:waaseyaa-reviewer:reviewer"
shell_pid: "7702"
history:
- timestamp: '2026-05-13T02:27:32Z'
  actor: spec-kitty.tasks
  event: wp_created
  notes: Generated as part of M-002 task materialization.
authoritative_surface: packages/migration/src/MigrationRunState.php
execution_mode: code_change
mission_id: 01KRCDE9ZXK2JEFPT6THSBVKNY
mission_slug: migration-platform-v1-01KRCDE9
owned_files:
- packages/migration/src/MigrationRunState.php
- packages/migration/src/Schema/MigrationRunStateSchema.php
- packages/migration/migrations/2026_05_13_000002_create_migration_run_state.php
- packages/cli/src/Command/Import/ImportResumeCommand.php
- packages/migration/tests/Unit/MigrationRunStateTest.php
- packages/migration/tests/Integration/ResumeFlowTest.php
- packages/cli/tests/Unit/Command/Import/ImportResumeCommandTest.php
priority: p1
tags:
- layer-3
- layer-6
- schema
- resume
---

# WP07 — Resume + progress tracking

## Objective

Persist per-record progress to a new `migration_run_state` table and wire up the `import:resume` CLI command. After this WP merges, interrupted runs can pick up where they stopped without re-importing already-imported records, and `import:status` (from WP06) reads real `failed` and `skipped` counts.

## Dependencies

- Internal: WP04 (id-map; resume looks at both id-map and run-state), WP06 (`MigrationRunner` is extended to write per-record progress).
- External: None.
- Charter anchors: `migration_run_state` is **mission-internal infrastructure** — NOT charter §5.8 stable surface. Future schema changes do not require charter amendment. Document this clearly.

## Scope (in / out)

**In scope**
- `migration_run_state` table schema per data-model.md §4.2 (FR-038).
- `MigrationRunStateSchema` declarative + migration file (date-prefix `2026_05_13_000002_`).
- `MigrationRunState` repository (`@api` for the public methods used by the runner; the table schema itself is internal).
- `import:resume <migration-id>` CLI command (FR-037).
- Extension of `MigrationRunner` (WP06) to write per-record outcomes into `migration_run_state` and to honour resume mode.
- Integration test: 1000-record fixture, interrupt at 500, resume, verify final state.

**Out of scope**
- Resume across CLI reboots when the worktree changes — out of scope; both runs must use the same database file.
- Rollback's interaction with `migration_run_state` (separate concern; WP08).

## File-ownership note

WP04 owns `packages/migration/src/Schema/MigrationIdMapSchema.php` specifically (one file). WP07 owns `packages/migration/src/Schema/MigrationRunStateSchema.php` specifically (one file). The `Schema/` directory itself is shared by both WPs at the file level — explicit per-file ownership; no directory globs.

## Branch strategy

Planning/base branch: `main`. Merge target: `main`. Per-lane worktree. Run `spec-kitty agent action implement WP07 --agent opus`.

## Implementation guidance

### Subtask T039 — `migration_run_state` schema + migration file

**Purpose**: Ship the per-record progress table.

**FRs covered**: FR-038.

**Files**:
- `packages/migration/src/Schema/MigrationRunStateSchema.php` (new, ~100 lines).
- `packages/migration/migrations/2026_05_13_000002_create_migration_run_state.php` (new, ~70 lines).

**Steps**:
1. Schema per data-model.md §4.2:
   ```sql
   CREATE TABLE migration_run_state (
       migration_id      TEXT NOT NULL,
       source_id_hash    TEXT NOT NULL,
       run_id            TEXT NOT NULL,                  -- UUIDv7 per CLI invocation
       item_status       TEXT NOT NULL,                  -- 'success' | 'error' | 'skipped'
       error_code        TEXT NULL,
       error_message     TEXT NULL,
       position          INTEGER NOT NULL,               -- monotonically increasing per run
       updated_at        TEXT NOT NULL,                  -- ISO 8601 UTC
       PRIMARY KEY (migration_id, source_id_hash)
   );
   CREATE INDEX migration_run_state__run
       ON migration_run_state (migration_id, run_id, position);
   ```
   Note: `item_status` (not `status`) per the shell-compatibility rule referenced in data-model.md §4.2 — avoids confusion with the per-migration aggregate "state" surfaced by `import:status`.
2. The PRIMARY KEY `(migration_id, source_id_hash)` means re-runs OVERWRITE prior outcomes for the same record. This is intentional: the table tracks the latest outcome per record.
3. The index `(migration_id, run_id, position)` is what `import:resume` consults to find the highest `position` for a `run_id`.

**Validation**:
- [ ] Migration runs cleanly on a fresh SQLite DB.
- [ ] Both indexes exist.
- [ ] `down()` reversible.

### Subtask T040 — `MigrationRunState` repository

**Purpose**: The repository surface used by `MigrationRunner` and `ImportResumeCommand`.

**FRs covered**: FR-037, FR-038.

**Files**:
- `packages/migration/src/MigrationRunState.php` (new, ~240 lines).

**Steps**:
1. `final class MigrationRunState` (`@api` only for `latestPositionForRun()`, `lookupItem()`, `countByStatus()` — the upsert/internal-write methods are framework-internal; mark with `@internal` PHPDoc).
2. Constructor: `__construct(DatabaseInterface $database, ?LoggerInterface $logger = null)`.
3. `recordSuccess(string $migrationId, string $sourceIdHash, string $runId, int $position, ?\DateTimeImmutable $now = null): void` — `INSERT ... ON CONFLICT DO UPDATE` to set `item_status='success'`, clear error fields, bump position + updated_at.
4. `recordError(string $migrationId, string $sourceIdHash, string $runId, int $position, string $errorCode, string $errorMessage, ?\DateTimeImmutable $now = null): void` — same upsert; sets `item_status='error'` and the error fields.
5. `recordSkipped(string $migrationId, string $sourceIdHash, string $runId, int $position, ?\DateTimeImmutable $now = null): void` — sets `item_status='skipped'`.
6. `latestPositionForRun(string $migrationId, string $runId): ?int` — `SELECT MAX(position) FROM migration_run_state WHERE migration_id = ? AND run_id = ?`.
7. `latestRunForMigration(string $migrationId): ?string` — `SELECT run_id FROM migration_run_state WHERE migration_id = ? ORDER BY updated_at DESC LIMIT 1`. Used by `import:resume` to find the most recent interrupted run.
8. `lookupItem(string $migrationId, string $sourceIdHash): ?ItemState` where `ItemState` is a nested value object with the row's fields. Used by `import:resume` to decide skip-already-imported.
9. `countByStatus(string $migrationId): array{success: int, error: int, skipped: int}` — used by `import:status` (WP06) to populate `FAILED` and `SKIPPED` columns.
10. `deleteAllForMigration(string $migrationId): int` — used by `import:reset` (WP08).
11. Batch-mode helper `recordBatch(array $items): void` — optional `≤100` row insert per FR-038 wording. Default to per-record commits; batch is opt-in via a `--batch-size=N` option on `import:run` (defer that flag to a future enhancement; document but do not ship). The default path is per-record-commit.

**Validation**:
- [ ] Round-trip per method.
- [ ] `latestPositionForRun()` returns the highest position; subsequent runs of the same `run_id` continue from `position + 1`.
- [ ] `countByStatus()` totals match the underlying rows.

**Edge cases**:
- Two concurrent runs against the same migration is prevented by WP09's lock. Resume from a *previous* run is the only concurrency-relevant case here.

### Subtask T041 — Extend `MigrationRunner` to write progress

**Purpose**: Wire `MigrationRunState` into the per-record loop established by WP06.

**FRs covered**: FR-038, FR-046 (per-record error capture, completed here from WP06's in-memory placeholder).

**Files**:
- `packages/migration/src/Runner/MigrationRunner.php` (modify — WP06-owned, additive method body changes).

**Steps**:
1. Add `MigrationRunState` to the constructor as a new collaborator (optional nullable to preserve WP06's test scaffolding):
   ```php
   public function __construct(
       MigrationRegistry $registry,
       ProcessChainExecutor $chain,
       MigrationIdMap $idMap,
       LoggerInterface $logger,
       ?MigrationRunState $runState = null,                // nullable for backward compatibility
       \Closure $clock = null,
   ) { ... }
   ```
2. In the per-record loop body, after success/skip/error classification:
   - On success: `$runState?->recordSuccess($migrationId, $sourceIdHash, $runId, $position++, $now())`.
   - On skip (id-map hit): `$runState?->recordSkipped(...)`.
   - On error: `$runState?->recordError(...)`.
3. The `$position` counter starts at 1 (humans count from 1) and increments after each record.
4. Add a new method `runResume(string $migrationId, RunOptions $options): RunReport`:
   1. Resolve `MigrationDefinition` from registry.
   2. `$priorRunId = $runState->latestRunForMigration($migrationId)`. If null → no prior run, raise `\InvalidArgumentException` with a useful message (operator should run `import:run`, not `import:resume`).
   3. `$priorPosition = $runState->latestPositionForRun($migrationId, $priorRunId) ?? 0`.
   4. `$options = $options->withRunId($priorRunId)` (use existing UUIDv7, not a new one).
   5. Walk the source records; skip the first `$priorPosition` records by counting via the position counter starting at `$priorPosition + 1`. The source plugin is iterated normally — resume relies on the deterministic order of `records()` plus the per-record id-map check. Each record is checked against `MigrationIdMap::lookupDestination()`; if a destination exists and the hash matches, it's a skip (FR-031) — this is the redundancy that makes resume robust to source plugins that don't honor `position` literally.
   6. Otherwise the loop is identical to `run()`.

**Validation**:
- [ ] Unit test: `runResume` requires a prior run (raises if `latestRunForMigration` returns null).
- [ ] Unit test: `runResume` reuses the prior `run_id` (visible in the returned `RunReport`).
- [ ] Integration test (T043) covers the full flow.

**Edge cases**:
- A source plugin that produces a different order on resume than on the original run will cause records to be re-checked against the id-map (correct behavior, just slower). Document — source plugins SHOULD yield a deterministic order, but the framework recovers when they don't.

### Subtask T042 — `ImportResumeCommand`

**Purpose**: CLI front-end for `MigrationRunner::runResume()`.

**FRs covered**: FR-037.

**Files**:
- `packages/cli/src/Command/Import/ImportResumeCommand.php` (new, ~130 lines).
- `packages/cli/tests/Unit/Command/Import/ImportResumeCommandTest.php` (new).

**Steps**:
1. Command name: `import:resume`. Argument: `migration-id` (required).
2. Options (same as `import:run`): `--dry-run`, `--halt-on-error`, `--limit`.
3. `execute()`:
   - Build `RunOptions` (note: `runId` is set by `MigrationRunner::runResume()`, not by the command).
   - Resolve `MigrationRunner`. Call `runResume($migrationId, $options)`.
   - Catch `\InvalidArgumentException` from "no prior run" → print operator-friendly message + exit 1.
   - Otherwise print the report's summary line + per-record error table (cap 20 rows).
   - Exit 0 on full success, 1 otherwise.

**Validation**:
- [ ] `CommandTester`: resume-with-prior-run completes; resume-without-prior-run exits 1 with a clear error.

### Subtask T043 — Integration test: full resume flow

**Purpose**: End-to-end test of the resume cycle.

**FRs covered**: FR-037, FR-038 (composition).

**Files**:
- `packages/migration/tests/Integration/ResumeFlowTest.php` (new, ~280 lines).

**Steps**:
1. Setup: in-memory SQLite, register `migration_test_widget` (from WP05's fixture), prepare a 100-record fixture (small for unit-test speed; WP11 covers 1000).
2. Test 1 — interrupt-then-resume:
   - Run with `--limit=50`. Assert 50 entities created, 50 rows in `migration_run_state` with `item_status='success'`.
   - Run `runResume`. Assert 50 more entities created (total 100), 100 rows in `migration_run_state` all `'success'`, identical `run_id` across the two batches.
3. Test 2 — resume reuses run_id:
   - Capture the run_id from the first `--limit=50` run.
   - After resume, assert `latestRunForMigration()` still returns the same run_id.
4. Test 3 — idempotent resume:
   - Run with `--limit=50`, then `runResume`, then `runResume` again. Assert second resume is a no-op (every record present in id-map → all skipped).
5. Test 4 — resume with no prior run:
   - Fresh migration, call `runResume` directly. Assert raises `\InvalidArgumentException`.

**Validation**:
- [ ] All four tests green.
- [ ] Full suite green.

**Edge cases**:
- The test fixture source plugin must be deterministic — yield records in a stable order each call.

## Tests

- **Unit**: T040 (`MigrationRunStateTest`), T042 (`ImportResumeCommandTest`).
- **Integration**: T043.
- **Conformance**: WP10 covers resume semantics as part of the source conformance suite.

## Definition of Done

- [ ] All five subtasks complete.
- [ ] FR-037 + FR-038 cited in code as `@spec FR-037` / `@spec FR-038`.
- [ ] `composer phpstan` clean.
- [ ] `composer cs-check` clean (run twice).
- [ ] `bin/check-package-layers` clean.
- [ ] `bin/check-composer-policy` clean.
- [ ] `bin/audit-dead-code` clean.
- [ ] `./vendor/bin/phpunit` full suite green.
- [ ] `MigrationRunner` continues to work without `MigrationRunState` injected (backward-compatible — nullable).
- [ ] `migration_run_state` migration runs cleanly on a fresh SQLite DB.
- [ ] `import:status` (WP06) now displays real `FAILED` and `SKIPPED` counts sourced from `MigrationRunState::countByStatus()` — verify with a smoke check.
- [ ] `MigrationRunState` documented as internal/non-charter — neither the schema nor the class is on §5.8 stable surface (`@api` applies only to `latestPositionForRun()`, `lookupItem()`, `countByStatus()`).
- [ ] No `psr/log` imports.

## Risks

- **R1 — Concurrent CLI invocations corrupt `position`**: prevented by WP09's filesystem lock. Without WP09, two `import:run` invocations against the same migration could write conflicting `position` rows. Confirm at integration time once WP09 lands.
- **R2 — Per-record commits slow on large migrations**: 1M records × 1 transaction each = 1M transactions. Mitigate with the batch-mode opt-in (deferred to a future flag; document the lever). For the WP11 1000-record validation, per-record-commit is fast enough.
- **R3 — `run_id` collision across migrations**: UUIDv7 is timestamp-ordered; two migrations starting in the same millisecond share a prefix but not the full 128 bits. Collision probability is negligible; document.
- **R4 — Schema column name `status` reserved by zsh**: avoided — column is named `item_status`. No shell-compatibility issue.

## Reviewer guidance

- Check: `MigrationRunState` repository uses `DatabaseInterface`, not raw PDO.
- Check: column is `item_status`, not `status`.
- Check: `MigrationRunner` accepts a nullable `MigrationRunState` (preserves WP06 tests).
- Check: `runResume()` reuses the prior `run_id` (this is the FR-037 contract — single logical run, multiple physical invocations).
- Check: `latestPositionForRun()` returns the maximum position from the index, not from a row count.
- Verify: T043 Test 2 captures and asserts the run_id reuse.
- Verify: `import:status` now shows non-zero `FAILED`/`SKIPPED` after a run with intentional failures.
- Confirm: `MigrationRunState`'s `@api`-annotated methods are exactly the three read-side methods; write-side methods are `@internal`.

## Activity Log

- 2026-05-13T15:18:14Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=4038 – Started implementation via action command
- 2026-05-13T15:36:58Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=4038 – Ready for review — resume + progress tracking complete (T039-T043). 8179 tests pass; FR-037/FR-038 satisfied; import:status now shows real FAILED/SKIPPED counts.
- 2026-05-13T15:37:34Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=7702 – Started review via action command
