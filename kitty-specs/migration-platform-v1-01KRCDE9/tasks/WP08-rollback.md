---
work_package_id: WP08
title: Rollback
dependencies:
- WP04
- WP05
requirement_refs:
- FR-035
- FR-036
- FR-041
- FR-042
- FR-043
- FR-044
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T044
- T045
- T046
- T047
- T048
agent: "claude:opus:waaseyaa-reviewer:reviewer"
shell_pid: "12721"
history:
- timestamp: '2026-05-13T02:27:32Z'
  actor: spec-kitty.tasks
  event: wp_created
  notes: Generated as part of M-002 task materialization.
authoritative_surface: packages/migration/src/Runner/RollbackWalker.php
execution_mode: code_change
mission_id: 01KRCDE9ZXK2JEFPT6THSBVKNY
mission_slug: migration-platform-v1-01KRCDE9
owned_files:
- packages/migration/src/Runner/RollbackWalker.php
- packages/migration/src/Runner/RollbackReport.php
- packages/cli/src/Command/Import/ImportRollbackCommand.php
- packages/cli/src/Command/Import/ImportResetCommand.php
- packages/migration/tests/Unit/Runner/RollbackWalkerTest.php
- packages/migration/tests/Integration/RollbackTest.php
- packages/cli/tests/Unit/Command/Import/ImportRollbackCommandTest.php
- packages/cli/tests/Unit/Command/Import/ImportResetCommandTest.php
priority: p1
tags:
- stable-surface
- layer-3
- layer-6
- rollback
---

# WP08 — Rollback

## Objective

Ship the per-migration rollback orchestration (`RollbackWalker`), the two CLI commands (`import:rollback`, `import:reset`), and demonstrate best-effort semantics with per-record error logging. After this WP merges, operators can undo a migration and reset the id-map without manual SQL.

`EntityDestination::rollback()` is implemented by WP05 (per-record undo of a single entity). This WP delivers the orchestrator that walks the id-map and dispatches per-record rollbacks.

## Dependencies

- Internal: WP04 (`MigrationIdMap::walkReverseCreation()`, `deleteByHash()`), WP05 (`EntityDestination::rollback()` + `DestinationWriteException`).
- External: None.
- Charter anchors: §5.8 (proposed) — `RollbackWalker` (stable surface), `import:rollback` + `import:reset` CLI commands.

## Scope (in / out)

**In scope**
- `RollbackWalker` — walks `MigrationIdMap::walkReverseCreation()` and calls `$destination->rollback($writeResult)` per row, with best-effort semantics per FR-044.
- `RollbackReport` — value object summarizing rollback outcome (rows visited, rolled back, failed).
- `ImportRollbackCommand` — `bin/waaseyaa import:rollback <migration-id>` (FR-035, FR-043).
- `ImportResetCommand` — `bin/waaseyaa import:reset <migration-id>` (FR-036).
- Integration test: write 100 records via `MigrationRunner`, then rollback, assert zero remaining entities and zero remaining id-map rows.

**Out of scope**
- Cross-migration rollback ordering — per-migration only (decision documented in plan.md complexity tracking).
- Resume-aware rollback — rollback always walks the full id-map, regardless of resume state.
- Transactional rollback as a single DB transaction — rejected (research §2 D10 alternative). Per-record best-effort is the contract.

## Branch strategy

Planning/base branch: `main`. Merge target: `main`. Per-lane worktree. Run `spec-kitty agent action implement WP08 --agent opus`.

## Implementation guidance

### Subtask T044 — `RollbackReport` value object

**Purpose**: Carve a clean boundary between the rollback walker and the CLI command.

**FRs covered**: FR-044 (per-record reporting).

**Files**:
- `packages/migration/src/Runner/RollbackReport.php` (new, ~90 lines).

**Steps**:
1. `final readonly class RollbackReport` (`@api`):
   ```php
   public function __construct(
       public string $migrationId,
       public int $visited,
       public int $rolledBack,
       public int $failed,
       public list<RollbackError> $errors,         // capped at 100
       public \DateTimeImmutable $startedAt,
       public \DateTimeImmutable $finishedAt,
   ) {}
   ```
2. Nested `RollbackError`:
   ```php
   public string $sourceIdHash;
   public string $destinationEntityType;
   public string $destinationUuid;
   public string $code;
   public string $message;
   ```
3. `summaryLine(): string` returns `"<migrationId>: rollback complete (<rolledBack>/<visited>, <failed> failed)"`.

**Validation**:
- [ ] Round-trip test.

### Subtask T045 — `RollbackWalker`

**Purpose**: The per-migration rollback orchestrator.

**FRs covered**: FR-041 (per-record interface), FR-043 (walk order), FR-044 (best-effort + logging).

**Files**:
- `packages/migration/src/Runner/RollbackWalker.php` (new, ~220 lines).

**Steps**:
1. `final class RollbackWalker` (`@api`).
2. Constructor: `__construct(MigrationRegistry $registry, MigrationIdMap $idMap, LoggerInterface $logger, ?MigrationRunState $runState = null, \Closure $clock = null)`. `$runState` is optional (WP07-introduced); when present, the walker records each rollback outcome there too (mirroring WP07's `recordSuccess()`/`recordError()` semantics with new statuses `'rolled_back'` / `'rollback_failed'` — extend WP07's enum or use a separate field; recommended: add a `rollback_status` column in a follow-up; v1 ships without this and just logs).
3. `rollback(string $migrationId): RollbackReport`:
   1. Resolve `MigrationDefinition` from registry (or accept a missing definition gracefully — operators may rollback after deleting the manifest; in that case use `$destinationFactory` fallback. v1 requires the definition; document.).
   2. `$visited = $rolledBack = $failed = 0`. `$errors = []`.
   3. Walk `$idMap->walkReverseCreation($migrationId)` (FR-043 — already ordered DESC by `last_imported_at, last_run_id` per WP04).
   4. For each `WriteResult`:
      - `$visited++`.
      - Try `$definition->destination->rollback($writeResult)` (FR-041).
        - On success: `$idMap->deleteByHash($migrationId, $writeResult->sourceIdHash)` (or, if WP01's `WriteResult` doesn't carry `sourceIdHash`, the id-map's walk method must yield `(sourceIdHash, WriteResult)` tuples — verify WP04's signature at implementation time).
        - `$rolledBack++`.
      - Catch any `\Throwable`:
        - `$failed++`.
        - Append `RollbackError` (cap at 100).
        - Log on `entity.lifecycle` (`LoggerInterface::error()`).
        - Continue the walk (FR-044 best-effort).
   5. Construct + return `RollbackReport`.

**Validation**:
- [ ] Unit test: walk 5 successful rollbacks; assert `RollbackReport` totals.
- [ ] Unit test: walk with a mid-iteration rollback failure; assert the failed record is logged and the walk continues.
- [ ] Unit test: an empty id-map (no rows) yields `visited=0` and a clean report.

**Edge cases**:
- A rollback that finds the entity already deleted (zombie row in id-map) must succeed silently (WP05 `EntityDestination::rollback()` returns even when the entity is missing, then the walker removes the id-map row). Verified by the integration test.

### Subtask T046 — `ImportRollbackCommand`

**Purpose**: CLI front-end for `RollbackWalker::rollback()`.

**FRs covered**: FR-035, FR-043, FR-044.

**Files**:
- `packages/cli/src/Command/Import/ImportRollbackCommand.php` (new, ~140 lines).
- `packages/cli/tests/Unit/Command/Import/ImportRollbackCommandTest.php` (new).

**Steps**:
1. Command name: `import:rollback`. Argument: `migration-id` (required). Option: `--confirm` (bool flag, default false). Rollback is destructive — require `--confirm` to proceed; without it, exit with a warning and a hint.
2. `execute()`:
   - Without `--confirm`: print `"WARNING: import:rollback will delete <N> destination entities for migration '<id>'. Re-run with --confirm to proceed."`. Read the count from `$idMap->countForMigration($migrationId)`. Exit 0.
   - With `--confirm`: call `$walker->rollback($migrationId)`. Print report summary + error-table (cap 20 rows).
3. Exit code: 0 on full success; 1 if `$report->failed > 0`.

**Validation**:
- [ ] `CommandTester`: confirm-gate enforced; happy-path round-trip; per-record failure surfaces in error table.

### Subtask T047 — `ImportResetCommand`

**Purpose**: Clear the id-map for a migration. Does NOT delete destination entities. Per FR-036, this is "re-runs after reset re-import as new entities" — useful when source IDs have drifted and operators want to disconnect prior import history.

**FRs covered**: FR-036.

**Files**:
- `packages/cli/src/Command/Import/ImportResetCommand.php` (new, ~110 lines).
- `packages/cli/tests/Unit/Command/Import/ImportResetCommandTest.php` (new).

**Steps**:
1. Command name: `import:reset`. Argument: `migration-id` (required). Option: `--confirm` (bool flag, default false).
2. `execute()`:
   - Without `--confirm`: print `"WARNING: import:reset will delete <N> id-map entries for migration '<id>'. Destination entities will NOT be touched. Re-runs will re-import as new entities. Re-run with --confirm to proceed."`. Exit 0.
   - With `--confirm`: call `$idMap->deleteAllForMigration($migrationId)`. Also call `$runState?->deleteAllForMigration($migrationId)` (clear progress). Print `"<migrationId>: reset complete (<N> id-map rows + <M> run-state rows deleted)"`.
3. Exit code: 0 always (operator-driven destructive op; no per-record failures).

**Validation**:
- [ ] `CommandTester`: confirm-gate enforced; reset deletes id-map rows but NOT entity rows.

**Edge cases**:
- `import:reset` is the recovery path when destination entities exist but the id-map is corrupt or operators want a clean second import. Document in the WP12 source-reader-author guide.

### Subtask T048 — Integration test: full rollback flow

**Purpose**: End-to-end test of rollback against the WP05 test-fixture entity type.

**FRs covered**: FR-035, FR-036, FR-041, FR-042, FR-043, FR-044 (composition).

**Files**:
- `packages/migration/tests/Integration/RollbackTest.php` (new, ~260 lines).

**Steps**:
1. Setup: in-memory SQLite, register `migration_test_widget`, run id-map + run-state migrations.
2. Test 1 — happy-path rollback:
   - Run a migration with 100 records (use the WP05 fixture). Assert 100 entities + 100 id-map rows.
   - Call `RollbackWalker::rollback()`. Assert 0 entities + 0 id-map rows. `RollbackReport.rolledBack === 100`, `failed === 0`.
   - Assert reverse-creation order: the test captures `BeforeDeleteEvent` order and asserts entities are deleted newest-first.
3. Test 2 — best-effort with mid-walk failure:
   - After importing 10 records, manually delete entities 5 and 6 directly from the entity-storage schema (simulating drift). Call `rollback()`. Assert `RollbackReport.failed >= 0` (depends on whether `EntityDestination::rollback()` treats "already deleted" as success or failure — per WP05 docs it should be success). The id-map rows for 5 and 6 are still removed. The remaining 8 entities are removed cleanly.
4. Test 3 — access-denied rollback:
   - Register a `Gate` that denies `delete` for the test account. Run a migration of 5 records, then rollback. Assert 5 rollback failures logged on `entity.lifecycle`, all 5 entities still present, all 5 id-map rows still present.
5. Test 4 — `import:reset` happy path:
   - After a 10-record import, run reset. Assert 0 id-map rows, 0 run-state rows; the 10 entities are STILL present (key difference from rollback).
6. Test 5 — reset + re-run produces new entities:
   - After reset, run the same migration again. Assert 20 entities (the original 10 + 10 new). 10 id-map rows. (This verifies FR-036's "re-runs after reset re-import as new entities".)

**Validation**:
- [ ] All five tests green.
- [ ] Full suite green.

**Edge cases**:
- Test 2 simulates the "operator dropped entities outside the framework" scenario. The framework should not crash; it should log and continue.
- Test 5's "20 entities" assertion requires the test fixture entity type to use auto-increment IDs — confirm against WP05's `MigrationTestWidgetType`.

## Tests

- **Unit**: T044 (round-trip), T045 (`RollbackWalkerTest`), T046, T047 (CLI tester).
- **Integration**: T048 — five tests covering FR-035, FR-036, FR-041..FR-044.
- **Conformance**: WP10 — `DestinationConformanceTestCase` exercises `rollback()` (FR-051 idempotency).

## Definition of Done

- [ ] All five subtasks complete.
- [ ] All six FRs cited in code as `@spec FR-xxx`.
- [ ] `composer phpstan` clean.
- [ ] `composer cs-check` clean (run twice).
- [ ] `bin/check-package-layers` clean.
- [ ] `bin/check-composer-policy` clean.
- [ ] `bin/audit-dead-code` clean.
- [ ] `./vendor/bin/phpunit` full suite green.
- [ ] `RollbackWalker` is `final class` and `@api`.
- [ ] `RollbackReport` is `final readonly class` and `@api`.
- [ ] Both CLI commands require `--confirm` to proceed (destructive-op gate).
- [ ] Per-record rollback errors are logged on `entity.lifecycle` channel (not `error_log`).
- [ ] Reverse-creation walk order is honoured — verified by T048 Test 1.
- [ ] `import:reset` does NOT delete destination entities — verified by T048 Test 4.
- [ ] No `psr/log` imports.

## Risks

- **R1 — Reverse-creation order on tied timestamps**: handled by WP04's secondary sort on `last_run_id`. T048 Test 1 should also assert tied-timestamp tiebreaker — add a sub-assertion if test scenario produces ties.
- **R2 — Cross-migration rollback** (deferred): operators who rollback `wp_posts` before `wp_users` may end up with `LookupProcessor` references that miss on subsequent re-runs. Documented as operator concern; not a framework gap.
- **R3 — Rollback during a concurrent `import:run`**: prevented by WP09's filesystem lock (the lock applies to all `import:*` mutating commands). Until WP09 lands, document that operators must not run rollback against a migration currently being imported.
- **R4 — `--confirm` foot-gun**: operators expecting a dry-run from `import:rollback` without flags get a usage hint instead. Document clearly. Consider adding a future `--dry-run` flag to `import:rollback` that reports what would be deleted (defer).

## Reviewer guidance

- Check: `RollbackWalker` uses `MigrationIdMap::walkReverseCreation()` (the lazy generator), not a `findAll() + array_reverse()`.
- Check: per-record exceptions in `RollbackWalker` are caught and logged; the walk continues.
- Check: `import:rollback` requires `--confirm`.
- Check: `import:reset` requires `--confirm` AND does NOT touch entity rows (read T048 Test 4's assertions carefully).
- Check: `RollbackReport.failed` increments on each rollback error.
- Verify: T048 Test 3 (access-denied) shows id-map rows are NOT cleared on rollback failure — the next operator action can be a retry.
- Verify: `entity.lifecycle` log channel is the destination for rollback failure logs.
- Confirm: `import:reset` also clears the run-state table for the migration (T047 step 2).

## Activity Log

- 2026-05-13T15:42:02Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=9236 – Started implementation via action command
- 2026-05-13T15:59:28Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=9236 – Rollback + reset CLI complete; all gates green; 8201 tests pass
- 2026-05-13T16:00:25Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=12721 – Started review via action command
- 2026-05-13T16:04:12Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=12721 – WP08 approved: rollback platform complete. EntityDestination::rollback() replaces WP05 LogicException with real semantics (gate-check delete -> BeforeDeleteEvent -> EntityRepository::delete -> AfterDeleteEvent); RollbackWalker walks MigrationIdMap::walkReverseCreationWithKeys in reverse-creation order with best-effort per-record failure capture (FR-043, FR-044); RollbackReport/RollbackError value objects bound in-memory error capture; FR-042 idempotency verified by missing-entity no-op test; FR-036 import:reset clears id-map+run-state without touching entities; FR-035 import:rollback CLI exit 0/1/2. Cross-WP additive changes accepted: MigrationIdMap.deleteByHash + walkReverseCreationWithKeys, MigrationRunState.deleteAllForMigration @api alias, DestinationWriteException.entityDeleteDenied/entityDeleteFailed factories - all preserve existing semantics, full WP05/WP07 tests still green (8201/8201 OK). Sequential entity-delete-then-id-map-delete (non-transactional) accepted with sound retry rationale (entity-already-absent path makes re-rollback a clean no-op). Strict reverse-creation order verified at RollbackWalkerTest with pinned-timestamp fixtures; integration test verifies counters with same-second timestamps. Exit code 4 split correctly excluded as out-of-scope per canonical WP08 prompt. Gates: phpunit 8201 OK; cs-check clean; phpstan 1308 files OK; composer-policy OK; package-layers OK; dead-code warn-only baseline (zero on WP08 files). Smoke: import:rollback/reset on unknown migration exit=2; status/run unaffected; both commands visible in CLI list with FR annotations. Forwards: WP09 must wrap RollbackWalker::rollback and gate import:rollback/import:reset; WP11 reuses RollbackTest fixtures for full teardown verification.
