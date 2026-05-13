---
work_package_id: WP05
title: EntityDestination + storage coordinator integration
dependencies:
- WP01
- WP04
requirement_refs:
- FR-018
- FR-019
- FR-020
- FR-021
- FR-022
- FR-023
- FR-024
planning_base_branch: main
merge_target_branch: main
branch_strategy: lane
subtasks:
- T027
- T028
- T029
- T030
- T031
- T032
history:
- timestamp: '2026-05-13T02:27:32Z'
  actor: spec-kitty.tasks
  event: wp_created
  notes: Generated as part of M-002 task materialization.
authoritative_surface: packages/migration/src/Plugin/Destination/EntityDestination.php
execution_mode: code_change
mission_id: 01KRCDE9ZXK2JEFPT6THSBVKNY
mission_slug: migration-platform-v1-01KRCDE9
owned_files:
- packages/migration/src/Plugin/Destination/EntityDestination.php
- packages/migration/src/Plugin/Destination/EntityDestinationFactory.php
- packages/migration/src/Exception/DestinationWriteException.php
- packages/migration/tests/Integration/EntityDestinationTest.php
- packages/migration/tests/Integration/EntityDestinationRevisionsTest.php
- packages/migration/tests/Fixtures/EntityType/MigrationTestWidgetType.php
- packages/migration/tests/Fixtures/MigrationTestWidget.php
priority: p1
tags:
- stable-surface
- layer-3
- entity-integration
- cross-cutting
---

# WP05 — EntityDestination + storage coordinator integration

## Objective

Ship the default `DestinationPluginInterface` implementation: `EntityDestination`. The class writes through the entity-storage coordinator (M-001's `EntityRepository` + `EntityStorageCoordinator`), respects access policies, fires lifecycle events (`BeforeSaveEvent` / `AfterSaveEvent`), creates initial revisions on revisionable entity types (M-006 `RevisionableEntityStorageInterface`), and atomically updates the id-map.

This is the **single hottest-risk WP in the mission** because it composes three M-001 / M-006 surfaces (lifecycle events, revisionable storage, the storage coordinator) plus the `SaveContext::isImport()` extension. It is the only WP with an external prerequisite — and the prerequisite is MET.

## Dependencies

- Internal: WP01 (interfaces + DTOs), WP04 (`MigrationIdMap`, `SourceId`, `CanonicalForm`).
- External: **MET — M-001 (`entity-storage-v2-01KRCDDC`, squash-merged at commit `0f7e1809a`) shipped the lifecycle events (`BeforeSaveEvent`/`AfterSaveEvent`) and the revisionable storage API in its work-package programme.** This WP has no further external blockers. Reference `feedback_internal_version_sweep_mechanism.md` and `feedback_spec_kitty_manual_workflow.md` for context on M-001's landing state.
- Charter anchors: §5.8 (proposed) — `EntityDestination`, `DestinationWriteException`; §5.3 (existing — entity surface) extended additively by `SaveContext::isImport()`.

## Scope (in / out)

**In scope**
- `EntityDestination` final class implementing `DestinationPluginInterface` (FR-018, FR-019, FR-020, FR-021, FR-022, FR-023, FR-024).
- `EntityDestinationFactory` for runtime construction with the right collaborators (entity type manager, storage coordinator, gate, account, id-map, logger).
- `DestinationWriteException` typed exception (FR-045 continued).
- Test-fixture entity type `migration_test_widget` (NOT a real Minoo entity) — defined under `packages/migration/tests/Fixtures/EntityType/` and `packages/migration/tests/Fixtures/MigrationTestWidget.php`, loaded only via `autoload-dev`. Per CLAUDE.md gotcha: never put dev-only test bases under `src/`.
- Two integration tests: one covers the non-revisionable write/rollback/idempotency path, one covers the revisionable path (initial revision on first import, new revision on changed re-run, no revision on unchanged re-run).

**Cross-cutting modification (additive, M-001-owned file)**
- This WP also adds `isImport(): bool` to `packages/entity-storage/src/SaveContext.php` (an M-001-owned file). The method is **additive** (existing call sites untouched; default value `false` so all current behavior is preserved). The addition is documented in `tasks.md`'s "Cross-cutting modifications" section. Listing in `owned_files` would conflict with M-001's WP04 owner record, so this prompt does not include the file in `owned_files`; the implementer must verify the M-001 path exists and add the method via a focused, well-tested PHPUnit-driven change. If the file's shape is materially different from data-model.md §1.9, raise to the reviewer before extending the public surface.

**Out of scope**
- The CLI runner that calls `EntityDestination::write()` — WP06.
- The rollback orchestrator that consumes `walkReverseCreation()` — that lands in the downstream rollback work package. (`EntityDestination::rollback()` is implemented in this WP; the runner that calls it is a downstream step.)
- The `migration_run_state` table updates that record per-record outcomes — WP07.
- `MigrationConcurrencyException` — WP09.
- Conformance test bases — WP10.

## Branch strategy

Planning/base branch: `main`. Merge target: `main`. Per-lane worktree. Run `spec-kitty agent action implement WP05 --agent opus`.

## Implementation guidance

### Subtask T027 — Test-fixture entity type `migration_test_widget`

**Purpose**: Stand up the synthetic entity type used by WP05 integration tests AND by WP11 end-to-end validation. Avoids coupling the framework's tests to any Minoo entity.

**FRs covered**: T027 is the test substrate that lets us validate FR-018..FR-024 against a real entity type.

**Files**:
- `packages/migration/tests/Fixtures/MigrationTestWidget.php` (new, ~80 lines) — extends `Waaseyaa\Entity\ContentEntityBase`.
- `packages/migration/tests/Fixtures/EntityType/MigrationTestWidgetType.php` (new, ~120 lines) — registers the type via `EntityType` value object.
- `packages/migration/tests/Fixtures/EntityType/MigrationTestWidgetRevisionableType.php` (new, ~140 lines) — revisionable variant used by the revisions integration test.

**Steps**:
1. `MigrationTestWidget` extends `ContentEntityBase`. Constructor takes `(array $values = [])` and hardcodes `entityTypeId: 'migration_test_widget'`, `entityKeys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title']` (per CLAUDE.md "Entity subclass constructors" gotcha).
2. Fields: `id` (auto-increment int), `uuid` (string), `title` (string), `body` (text), `value_int` (int), `tags` (json array). All declared via `FieldDefinition` instances in the `EntityType` factory.
3. `MigrationTestWidgetType::create()` returns an `EntityType` with the field definitions + a storage handler that uses `SqlEntityStorage` (or the M-001 `EntityStorageCoordinator` if available) backed by an in-memory SQLite database for tests.
4. Revisionable variant: same field set, but the `EntityType` enables M-001/M-006's revisionable storage flag (`revisionable: true`).
5. Both types register through a test-only `TestEntityTypeProvider` exposing a `migrationPlugins()` equivalent for entity-type registration (use the M-001 convention).

**Validation**:
- [ ] `new MigrationTestWidget(['title' => 'x'])->save()` succeeds against an in-memory SQLite DB.
- [ ] The revisionable variant creates an initial revision on first save (verified via the M-001 `RevisionableEntityStorageInterface`).

**Edge cases**:
- The fixture must live under `tests/Fixtures/` and be autoloaded via `Waaseyaa\Migration\Tests\Fixtures\` (autoload-dev only, declared by WP01's composer.json).

### Subtask T028 — Add `SaveContext::isImport()` (cross-cutting, M-001-owned file)

**Purpose**: Extend `SaveContext` with a flag subscribers can read to detect import-driven saves. Signal-only — does not alter coordinator behavior.

**FRs covered**: FR-022.

**Files**:
- `packages/entity-storage/src/SaveContext.php` (modify, M-001-owned). Additive method only.
- `packages/entity-storage/tests/Unit/SaveContextTest.php` (modify or add — verify the new method).

**Steps**:
1. Verify `SaveContext` exists at `packages/entity-storage/src/SaveContext.php` (M-001 WP04 deliverable; should be present at squash-merge commit `0f7e1809a`). If it isn't there, halt and escalate — the M-001 dependency was misrepresented.
2. Add a `public readonly bool $isImport` constructor parameter with default `false`. Default preserves all existing call sites unchanged.
3. PHPDoc: explain the flag is set by `EntityDestination` during migration writes; subscribers may use it to skip expensive non-essential work during bulk imports (e.g. cache invalidation, search-index refresh).
4. Update the unit test (or add a new one) asserting:
   - Default construction yields `isImport === false`.
   - `new SaveContext(isImport: true)` yields `isImport === true`.
   - Existing constructor parameters still round-trip.
5. **Do NOT** modify `EntityStorageCoordinator`, `BeforeSaveEvent`, or `AfterSaveEvent`. The flag is a passive property; subscribers read it from the `$saveContext` already passed to the events.

**Validation**:
- [ ] `SaveContextTest` covers both default and `isImport: true` construction.
- [ ] All existing `entity-storage` tests still pass — verify with `./vendor/bin/phpunit packages/entity-storage/`.
- [ ] Full suite green.

**Edge cases**:
- A consumer constructing `SaveContext` with named arguments must continue to work. Confirm by running M-001's existing tests; they exercise the constructor extensively.

### Subtask T029 — `EntityDestination` class

**Purpose**: The main deliverable. Implements `DestinationPluginInterface` over `EntityRepository` / `EntityStorageCoordinator`.

**FRs covered**: FR-018, FR-019, FR-020, FR-021, FR-023, FR-024.

**Files**:
- `packages/migration/src/Plugin/Destination/EntityDestination.php` (new, ~360 lines).
- `packages/migration/src/Plugin/Destination/EntityDestinationFactory.php` (new, ~120 lines).

**Steps**:
1. `final class EntityDestination implements DestinationPluginInterface` (`@api`).
2. Constructor:
   ```php
   public function __construct(
       private EntityTypeManager $entityTypeManager,
       private EntityRepositoryRegistry $repositories,   // M-001's per-entity-type repository accessor
       private GateInterface $gate,
       private AccountInterface $account,
       private MigrationIdMap $idMap,
       private DBALDatabase $database,                   // for transactional()
       private LoggerInterface $logger,
       private string $entityType,
       private ?string $bundle = null,
       private ?string $langcode = null,
   ) {}
   ```
3. `id(): string` → `'entity'`. `stability(): string` → `'stable'`.
4. `write(DestinationRecord $record): WriteResult` per data-model.md §5.2:
   1. Resolve `EntityTypeInterface` via `EntityTypeManager::getDefinition($this->entityType)`. Missing → `DestinationWriteException` code `'entity_type_unknown'`.
   2. Compute `source_record_hash` via `CanonicalForm::encode($record->values)` then `hash('sha256', ...)`.
   3. Look up id-map. Row exists + hash matches → return the existing `WriteResult` (skip; FR-031).
   4. Resolve bundle (`$record->bundle ?? $this->bundle`) — bundle resolves at write time per D8.
   5. `Gate::denies('create', $entityTypeDefinition, $this->account)` → if denied, raise `DestinationWriteException` code `'entity_create_denied'`, log on `entity.lifecycle` channel.
   6. Wrap steps 7–9 in `$this->database->transactional(function () { ... })` for FR-029 atomicity.
   7. If id-map row exists + hash differs → load entity by uuid via `EntityRepository::find($uuid)`, call `setValues($record->values)`, save with new `SaveContext(isImport: true)`. M-006 revisionable storage creates a new revision automatically when the storage type is revisionable (FR-023).
   8. If no id-map row → construct a new entity via the repository's create method, set values, call `enforceIsNew()` if pre-set IDs are present (CLAUDE.md gotcha), save with new `SaveContext(isImport: true)`. Storage dispatches `BeforeSaveEvent` + `AfterSaveEvent` carrying the import-flagged `SaveContext` (FR-021).
   9. `MigrationIdMap::upsert()` to update/create the id-map row inside the same transaction.
   10. Construct and return a `WriteResult` carrying `destinationEntityType`, `destinationUuid`, `sourceRecordHash`, `runId`, `writtenAt`.
5. `rollback(WriteResult $result): void` per data-model.md §5.3:
   1. Load the entity by uuid via the repository. If null (already deleted), remove the id-map row and return (best-effort, log at info).
   2. `Gate::denies('delete', $entity, $this->account)` → if denied, log on `entity.lifecycle` and skip (per FR-044 best-effort semantics).
   3. `transactional()` wrap: `EntityRepository::delete($entity)` (M-001 dispatches `BeforeDeleteEvent` + `AfterDeleteEvent`), then `MigrationIdMap::delete($result->migrationId, ???)`. The id-map delete needs the `SourceId` to compute the primary key — extend `WriteResult` from WP01 to also carry `sourceIdHash` so rollback can find the row. (Add a `public string $sourceIdHash` field via WP01 amendment — file a follow-up in WP01's owned-files surface; alternative: keep `MigrationIdMap` API ergonomic by accepting `(string $migrationId, string $sourceIdHash)` for delete.)

   Decision: extend `MigrationIdMap::deleteByHash(string $migrationId, string $sourceIdHash): bool` in this WP — adds a method to `MigrationIdMap` (WP04-owned file). Acceptable additive change because the method is new; documented in WP04's owned-files surface, with WP05 owning the implementation pull through. Reviewer should confirm.
6. `lookup(SourceId $sourceId): ?WriteResult` (FR-028 via this destination plugin): delegates to `MigrationIdMap::lookupDestination($this->bindMigrationId(), $sourceId)`. The migration id is bound via `withMigration(string $migrationId): self` (returns a new instance with the migration id set — required because plugins are constructed before the migration id is known). Document in PHPDoc.
7. `EntityDestinationFactory`:
   - `forEntityType(string $entityType, ?string $bundle = null, ?string $langcode = null): EntityDestination` — service-located factory that pulls collaborators from the DI container. Used in `MigrationDefinition` declarations.

**Validation**:
- [ ] Write path covered by T031 integration test (round-trips a single record, asserts entity exists + id-map row exists + lifecycle events fired with `isImport === true`).
- [ ] Re-run with unchanged source hash skips the write (no new entity, id-map row unchanged).
- [ ] Re-run with changed source hash updates fields and creates a new revision (revisionable variant only).
- [ ] Access-denied on `create` raises `DestinationWriteException`.
- [ ] Rollback deletes the entity AND removes the id-map row.

**Edge cases**:
- Multi-backend entity types (e.g. one with a vector field on a separate backend): the storage coordinator fans out automatically per ADR 010. The integration test in T031 uses a single-backend type for clarity; multi-backend is covered by spec FR-019 and exercised by M-001's coordinator tests.
- A `DestinationRecord` whose `$values` omit a required field: surfaces as an `EntityValidationException` from `EntityRepository::save()`. Catch and wrap as `DestinationWriteException` code `'entity_validation_failed'` to keep the destination plugin's error surface uniform.
- The `enforceIsNew()` call (CLAUDE.md gotcha): only needed when the migration sets IDs explicitly. For normal create paths with auto-increment IDs, omit it.

### Subtask T030 — `DestinationWriteException`

**Purpose**: Typed exception for destination-plugin write failures.

**FRs covered**: FR-045 (continued).

**Files**:
- `packages/migration/src/Exception/DestinationWriteException.php` (new, ~70 lines).

**Steps**:
1. Extends `\RuntimeException`. `@api`. Public readonly `string $code` (string code, not the integer `Throwable::$code`), `string $migrationId`, `string $entityType`, `?\Throwable $previous = null`.
2. `public const CODES`: `'ENTITY_TYPE_UNKNOWN'`, `'ENTITY_CREATE_DENIED'`, `'ENTITY_DELETE_DENIED'`, `'ENTITY_VALIDATION_FAILED'`, `'ID_MAP_UPSERT_FAILED'`.
3. Message format: `"Destination write failed for migration '<migrationId>' (entity_type=<entityType>, code=<code>): <previousMessage>"`.

**Validation**:
- [ ] Round-trip test covering each `CODES` value.

### Subtask T031 — Integration test: non-revisionable round-trip

**Purpose**: Prove the write/rollback/idempotency flow end-to-end against a real entity type.

**FRs covered**: FR-018, FR-019, FR-020, FR-021, FR-022, FR-029 (atomicity), FR-030, FR-031.

**Files**:
- `packages/migration/tests/Integration/EntityDestinationTest.php` (new, ~260 lines).

**Steps**:
1. `setUp()`: create an in-memory SQLite DB, run the entity-storage schema migrations (M-001 framework provides the bootstrap), run WP04's `migration_id_map` migration, register the `migration_test_widget` entity type via a test-local `ServiceProvider`.
2. Test A — first write: a single `SourceRecord` round-trips. Assert: entity row exists in the storage schema; id-map row exists with the expected hash; `BeforeSaveEvent` + `AfterSaveEvent` were dispatched (use a recording subscriber); the captured `SaveContext::$isImport === true`.
3. Test B — idempotent re-run: write twice with identical `DestinationRecord`. Assert: only one entity exists; id-map row not duplicated; the second `write()` returns the same `WriteResult` (uuid identical); no `BeforeSaveEvent` fired on the second call.
4. Test C — changed re-run: write, then mutate `$values['title']`, then write again. Assert: entity row updated; id-map row's `source_record_hash` updated; `BeforeSaveEvent` fired exactly once on the second call.
5. Test D — access-denied: register a `Gate` that denies `create` for the test account; `write()` raises `DestinationWriteException` code `'ENTITY_CREATE_DENIED'`; assert log entry on `entity.lifecycle`.
6. Test E — atomicity: inject a `MigrationIdMap` double that throws on `upsert()`; assert the entity is NOT persisted (transactional rollback).
7. Test F — rollback: write, then `rollback()`. Assert: entity row gone; id-map row gone; `BeforeDeleteEvent` + `AfterDeleteEvent` fired.

**Validation**:
- [ ] All six tests green.
- [ ] Full suite green.

**Edge cases**:
- The recording event subscriber is built per-test; do not register globally (interferes with other tests).
- The `Gate` test double should implement the M-001 `GateInterface` shape (verify path: `packages/access/src/Gate/GateInterface.php`).

### Subtask T032 — Integration test: revisionable variant

**Purpose**: Prove FR-023 — initial revision on first import; new revision on changed re-run; no revision on unchanged re-run.

**FRs covered**: FR-023.

**Files**:
- `packages/migration/tests/Integration/EntityDestinationRevisionsTest.php` (new, ~190 lines).

**Steps**:
1. Use the revisionable variant of `migration_test_widget` from T027.
2. Test 1 — first import: `write()` once; assert exactly one revision exists in the M-006 revision table for this entity uuid.
3. Test 2 — unchanged re-run: `write()` twice with identical values; assert still exactly one revision (no new revision on skip path).
4. Test 3 — changed re-run: `write()`, mutate `body`, `write()` again; assert two revisions exist; the latest revision contains the new body.
5. Test 4 — revision metadata: the revision is created with a `RevisionMetadata` (or whatever M-006 named it) carrying the import-run id and a comment indicating it was created by `EntityDestination`. Confirm by reading the revision row.

**Validation**:
- [ ] All four tests green.
- [ ] Full suite green.

**Edge cases**:
- The exact `RevisionableEntityStorageInterface` shape is M-006's deliverable; resolve the method name (`createRevision()` vs `saveRevision()`) at implementation time by reading `packages/entity-storage/src/Revision/`.

## Tests

- **Unit**: T028's `SaveContextTest` update; T030's `DestinationWriteExceptionTest`.
- **Integration**: T031 + T032.
- **Conformance**: WP10 — `EntityDestination` is the canonical reference for the destination conformance suite.

## Definition of Done

- [ ] All six subtasks complete.
- [ ] All seven FRs cited in code as `@spec FR-xxx`.
- [ ] `composer phpstan` clean.
- [ ] `composer cs-check` clean (run twice).
- [ ] `bin/check-package-layers` clean (Layer 3 → Layer 0/1; no upward edges).
- [ ] `bin/check-composer-policy` clean.
- [ ] `bin/audit-dead-code` clean.
- [ ] `./vendor/bin/phpunit` full suite green — **especially** `packages/entity-storage/tests/`, which exercises the additive `SaveContext::isImport()` extension.
- [ ] `EntityDestination` is `final class`.
- [ ] `EntityDestination::write()` and `rollback()` are wrapped in `DBALDatabase::transactional()` (`.claude/rules/entity-storage-invariant.md`).
- [ ] No raw PDO usage anywhere.
- [ ] `BeforeSaveEvent` / `AfterSaveEvent` carry `SaveContext::isImport === true` during import writes (asserted by T031 Test A).
- [ ] Revisionable variant creates initial revision on first write and a new revision on changed re-run (asserted by T032).
- [ ] All test-fixture entity classes are under `packages/migration/tests/Fixtures/` and autoloaded via `autoload-dev` only.

## Risks

- **R1 — `SaveContext` shape divergence**: M-001 may have shipped `SaveContext` with a different constructor signature than data-model.md §1.9 anticipates. Mitigation: T028 step 1 reads the file before extending it; halt + escalate if the shape is unexpected.
- **R2 — Backend fan-out partial failure** (research §8 R6): multi-backend entity types may have one backend succeed and another fail. M-001's `PartialSaveException` is the surface. `EntityDestination::write()` must let `PartialSaveException` propagate (do not catch it as `DestinationWriteException` — operators need to see the partial-save semantics). Document.
- **R3 — `isImport` flag piping bug** (research §8 R7): if `EntityDestination` forgets to set the flag, subscribers think the save is interactive. Mitigation: T031 Test A asserts the flag is observed in the dispatched event.
- **R4 — `MigrationIdMap::deleteByHash()` API expansion**: this WP adds a method to a WP04-owned class. Conflict resolution: WP04 ships the method; WP05 owns its use. Document the extension in both WPs' Cross-cutting modifications.
- **R5 — `enforceIsNew()` misuse**: setting it unconditionally on every create path makes auto-increment fail. Mitigation: only set when `$record->values` contains a non-null primary-key field.

## Reviewer guidance

- Check: `EntityDestination` uses `EntityRepository::save()` exclusively. No raw `Database::insert()` for entities (`.claude/rules/entity-storage-invariant.md`).
- Check: `transactional()` wraps the entity save AND the id-map upsert in the same block — verify by reading the call sequence.
- Check: `SaveContext` extension is additive only (default value preserves existing call sites).
- Check: `BeforeSaveEvent` listener in T031 Test A asserts `$event->saveContext->isImport === true`.
- Verify: Test E (atomicity) actually fails the test if the rollback doesn't work — break the transactional wrap deliberately during review to confirm the test catches it.
- Verify: the revisionable variant is exercised against M-006's `RevisionableEntityStorageInterface` — not a mock.
- Confirm: `EntityDestinationFactory` is constructor-injected, not service-located.
- Confirm: `WriteResult::sourceIdHash` (if added) is also updated in WP01's prompt amendment, OR the implementation uses `SourceId` recompute on rollback instead of storing the hash — pick one and document.
