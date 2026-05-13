---
work_package_id: WP04
title: ID-mapping + SourceId + idempotency primitives
dependencies:
- WP01
requirement_refs:
- FR-025
- FR-026
- FR-027
- FR-028
- FR-029
- FR-030
- FR-031
planning_base_branch: main
merge_target_branch: main
branch_strategy: lane
subtasks:
- T021
- T022
- T023
- T024
- T025
- T026
history:
- timestamp: '2026-05-13T02:27:32Z'
  actor: spec-kitty.tasks
  event: wp_created
  notes: Generated as part of M-002 task materialization.
authoritative_surface: packages/migration/src/MigrationIdMap.php
execution_mode: code_change
mission_id: 01KRCDE9ZXK2JEFPT6THSBVKNY
mission_slug: migration-platform-v1-01KRCDE9
owned_files:
- packages/migration/src/SourceId.php
- packages/migration/src/MigrationIdMap.php
- packages/migration/src/Schema/MigrationIdMapSchema.php
- packages/migration/migrations/2026_05_13_000001_create_migration_id_map.php
- packages/migration/src/Exception/SourceReadException.php
- packages/migration/src/Canonical/CanonicalForm.php
- packages/migration/tests/Unit/SourceIdTest.php
- packages/migration/tests/Unit/MigrationIdMapTest.php
- packages/migration/tests/Unit/Canonical/CanonicalFormTest.php
- packages/migration/tests/Integration/MigrationIdMapIntegrationTest.php
priority: p1
tags:
- stable-surface
- layer-3
- schema
---

# WP04 — ID-mapping + SourceId + idempotency primitives

## Objective

Replace the WP01 `SourceId` stub with the real implementation, ship the `migration_id_map` table schema, deliver `MigrationIdMap` (the boot-time-stable lookup surface), `CanonicalForm` (deterministic hashing of source records and destination payloads), and `SourceReadException`. After this WP merges, idempotent re-runs, re-import-on-change, and rollback's reverse-creation walk all have the data substrate they need.

This WP can run in parallel with WP02 and WP03 — all three depend only on WP01.

## Dependencies

- Internal: WP01 (`SourceId` stub to replace, `WriteResult` value object).
- External: None. Uses M-001's `Waaseyaa\Foundation\Database\DBALDatabase` for transactional access (`Connection::transactional()`).
- Charter anchors: §5.8 (proposed) — `SourceId`, `MigrationIdMap`, `migration_id_map` table schema, `SourceReadException`.

## Scope (in / out)

**In scope**
- Full `SourceId` implementation (replacing WP01 stub) — `hash()` returns a deterministic sha256 of canonical form (FR-027).
- `migration_id_map` table schema per spec §8.1 (FR-025).
- Waaseyaa migration file (`migrations/...`) creating the table — discovered by `MigrationLoader` from M-001's migration system. Filename `2026_05_13_000001_create_migration_id_map.php` (date-prefixed, see CLAUDE.md migration system boot order gotcha).
- `MigrationIdMap` repository (`@api`) with `lookupDestination()`, `upsert()`, `delete()`, `walkReverseCreation()`. Uses `DatabaseInterface` for non-entity table access per `.claude/rules/entity-storage-invariant.md`.
- `CanonicalForm` helper — sha256 of a canonical-form JSON encoding (sorted keys, `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`, `JSON_THROW_ON_ERROR`).
- `SourceReadException` typed exception (FR-045 continued).
- Integration test driving the table through `DBALDatabase::createSqlite()` (in-memory).

**Out of scope**
- The `EntityDestination` write/rollback paths that *call* `MigrationIdMap` — that's WP05.
- The `migration_run_state` schema — WP07.
- `MigrationConcurrencyException` — WP09.

## Branch strategy

Planning/base branch: `main`. Merge target: `main`. Per-lane worktree. Run `spec-kitty agent action implement WP04 --agent opus`.

## Implementation guidance

### Subtask T021 — Real `SourceId` (replaces WP01 stub)

**Purpose**: Replace the WP01 forward-stub with a fully-functional `SourceId` value object whose `hash()` produces a deterministic sha256.

**FRs covered**: FR-026, FR-027.

**Files**:
- `packages/migration/src/SourceId.php` (replace WP01's stub, ~80 lines).
- `packages/migration/src/Canonical/CanonicalForm.php` (new, ~80 lines) — extracted helper used by both `SourceId::hash()` and `MigrationIdMap`'s `source_record_hash` calculator.

**Steps**:
1. Delete the WP01 stub's `\LogicException` body. Implement `hash(): string` as:
   ```php
   return hash('sha256', CanonicalForm::encode([
       'source_type' => $this->sourceType,
       'keys' => $this->keys,
   ]));
   ```
2. `CanonicalForm::encode(array $value): string`:
   - Recursively sort associative arrays by key.
   - JSON-encode with `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR`.
   - Numeric-keyed arrays preserve original order (they encode as JSON arrays, not objects).
   - Booleans encode as `true`/`false`; null encodes as `null`; integers do NOT cast to strings.
3. Constructor validation (re-check from WP01): `$sourceType` non-empty matches `/^[a-z][a-z0-9_]*$/`; `$keys` non-empty associative array; each key non-empty string; each value scalar or null (no nested arrays — keep keying flat for hash stability).
4. `equals(SourceId $other): bool` convenience method comparing `hash()` outputs.

**Validation**:
- [ ] `hash()` is deterministic — 1,000 invocations yield identical output.
- [ ] Two `SourceId`s with `keys` declared in different insertion orders produce the same hash (key sort works).
- [ ] Two `SourceId`s differing only in `sourceType` produce different hashes.
- [ ] Validation rejects nested-array keys.

**Edge cases**:
- Integer keys: `['id' => 42]` and `['id' => '42']` must produce different hashes (integers and strings are distinct in the canonical form). Document in PHPDoc — source plugins are responsible for type-stable key values.
- Unicode in keys: the canonical form must preserve composed-vs-decomposed Unicode forms verbatim (no NFC normalization in v1).

### Subtask T022 — `migration_id_map` schema + migration file

**Purpose**: Ship the stable-surface schema for the id-map table. Future schema changes require a charter amendment.

**FRs covered**: FR-025.

**Files**:
- `packages/migration/src/Schema/MigrationIdMapSchema.php` (new, ~110 lines) — declarative schema class describing columns + indexes; used by the migration file and by tests that build the schema directly (e.g. on `:memory:` SQLite).
- `packages/migration/migrations/2026_05_13_000001_create_migration_id_map.php` (new, ~70 lines) — Waaseyaa migration class extending the framework's migration base (see how M-001's WP07 revision-schema migration was packaged for the template; if it landed under `packages/entity-storage/migrations/`, mirror that pattern).

**Steps**:
1. `MigrationIdMapSchema` exposes static `tableName()`, `columns()`, `indexes()` returning structured arrays the migration file consumes. Keeps the schema declarable once and reusable.
2. Migration class implements the framework's `MigrationInterface` (see `packages/foundation/src/Migration/` or wherever M-001 placed it; resolve by `rg 'interface MigrationInterface' packages/`). `up()` creates the table per spec §8.1:
   ```sql
   CREATE TABLE migration_id_map (
       migration_id        TEXT NOT NULL,
       source_id_hash      TEXT NOT NULL,
       destination_entity_type TEXT NOT NULL,
       destination_uuid    TEXT NOT NULL,
       last_imported_at    TEXT NOT NULL,
       last_run_id         TEXT NOT NULL,
       source_record_hash  TEXT NOT NULL,
       PRIMARY KEY (migration_id, source_id_hash)
   );
   CREATE INDEX migration_id_map__entity ON migration_id_map(destination_entity_type, destination_uuid);
   ```
3. `down()` drops the table.
4. Use `DatabaseInterface::execute()` for portable DDL (works across SQLite + MySQL + PostgreSQL); avoid raw PDO per `.claude/rules/entity-storage-invariant.md`.

**Validation**:
- [ ] Migration runs cleanly on a fresh SQLite database: `DBALDatabase::createSqlite()` + `Migrator::up()` produces the expected schema.
- [ ] `down()` is reversible — `up(); down(); up()` succeeds.
- [ ] Both indexes exist after `up()` (assert via SQLite's `pragma_index_list`).

**Edge cases**:
- The migration filename's date prefix (`2026_05_13_000001_`) must sort before any future migration in the same package — establish the convention here.

### Subtask T023 — `MigrationIdMap` repository

**Purpose**: The stable-surface API for reading/writing id-map rows.

**FRs covered**: FR-028, FR-029, FR-030, FR-031.

**Files**:
- `packages/migration/src/MigrationIdMap.php` (new, ~220 lines).

**Steps**:
1. `final class MigrationIdMap` (`@api`). Constructor `__construct(DatabaseInterface $database, ?LoggerInterface $logger = null)`.
2. `lookupDestination(string $migrationId, SourceId $sourceId): ?WriteResult` (FR-028): `SELECT` row by `(migration_id, source_id_hash)`; if found, hydrate a `WriteResult`. The lookup must NOT include the source-record hash check — that comparison is the caller's job (WP05 uses it to decide skip-vs-update).
3. `upsert(string $migrationId, SourceId $sourceId, string $destinationEntityType, string $destinationUuid, string $sourceRecordHash, string $runId, ?\DateTimeImmutable $now = null): WriteResult` (FR-029): builds the row, runs `INSERT ... ON CONFLICT (migration_id, source_id_hash) DO UPDATE SET ...` (SQLite + Postgres support `ON CONFLICT`; MySQL uses `INSERT ... ON DUPLICATE KEY UPDATE` — abstract via `DatabaseInterface::insertOrUpdate()` if it exists, or emit driver-specific SQL using `$database->getConnection()->getDatabasePlatform()->getName()`). `$now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))`. Returns the `WriteResult` reflecting the new row.
4. `delete(string $migrationId, SourceId $sourceId): bool` (FR-029): deletes by primary key; returns true if a row was removed.
5. `deleteAllForMigration(string $migrationId): int` (FR-036 use case from WP08): deletes all rows for a migration; returns the row count. Logs at info.
6. `walkReverseCreation(string $migrationId): iterable<WriteResult>` (FR-043 use case from WP08): yields rows ordered by `(last_imported_at DESC, last_run_id DESC)` per data-model.md §8 — note `last_run_id` is the secondary sort for clock-tie deterministic ordering (R4 risk). Lazy via a `\Generator`; each yield is a `WriteResult`.
7. `countForMigration(string $migrationId): int`: used by `import:status` (WP06).
8. **Atomicity (FR-029)**: `upsert()` does NOT open its own transaction. The caller (`EntityDestination::write()` — WP05) is expected to wrap the entity save + id-map upsert in a single `DBALDatabase::transactional()` block. Document this in PHPDoc and provide a `transactional(callable $body): mixed` helper that the caller can use (delegates to `DBALDatabase::transactional()`).

**Validation**:
- [ ] Insert + lookup round-trip.
- [ ] Upsert with same primary key updates fields (no duplicate row).
- [ ] `delete()` removes only the targeted row.
- [ ] `deleteAllForMigration()` is scoped — never deletes other migrations' rows.
- [ ] `walkReverseCreation()` yields in the documented order; tied timestamps tie-break by `last_run_id`.

**Edge cases**:
- A `lookupDestination()` for an unknown id returns null, never raises.
- `walkReverseCreation()` yields lazily — confirmed by writing 10,000 rows and asserting memory does not grow proportionally during the walk.

### Subtask T024 — `SourceReadException`

**Purpose**: Typed exception for source-plugin read failures. Bridges plugin-level errors to the runner.

**FRs covered**: FR-045 (continued).

**Files**:
- `packages/migration/src/Exception/SourceReadException.php` (new, ~50 lines).

**Steps**:
1. Extends `\RuntimeException`. `@api`. Public readonly `string $sourceId` (the source-plugin id), `string $migrationId`, `?\Throwable $previous = null`. Stable `public const CODE = 'SOURCE_IO_ERROR'`.
2. Message format: `"Source plugin '<sourceId>' failed for migration '<migrationId>': <previousMessage>"`.

**Validation**:
- [ ] Round-trip test.

### Subtask T025 — Unit tests

**Purpose**: PHPUnit unit tests for `SourceId`, `CanonicalForm`, `MigrationIdMap`, `SourceReadException`.

**FRs covered**: test coverage for FR-025..FR-031.

**Files**:
- `packages/migration/tests/Unit/SourceIdTest.php` (new).
- `packages/migration/tests/Unit/Canonical/CanonicalFormTest.php` (new).
- `packages/migration/tests/Unit/MigrationIdMapTest.php` (new, ~280 lines).
- `packages/migration/tests/Unit/Exception/SourceReadExceptionTest.php` (new).

**Steps**:
1. `SourceIdTest`: cover hash determinism, key-order independence, source-type sensitivity, validation rejections.
2. `CanonicalFormTest`: cover key sorting, scalar handling, Unicode preservation, JSON-encoding flags, throw-on-error behavior.
3. `MigrationIdMapTest`: use `DBALDatabase::createSqlite()` in-memory; run the migration; exercise every public method. Use `DBALDatabase` directly — do NOT mock `DatabaseInterface` (final-class mock gotcha). Pattern: anonymous `setUp()` creates a real in-memory DB and runs the migration; tests share the connection.
4. `SourceReadExceptionTest`: round-trip + message format.

**Validation**:
- [ ] All four test files green.
- [ ] Coverage of `MigrationIdMap` ≥ 90% line.

### Subtask T026 — Integration test: schema portability + transactional safety

**Purpose**: Drive the table through a real DBAL transaction and assert atomicity across platforms.

**FRs covered**: FR-029 (transactional atomicity invariant).

**Files**:
- `packages/migration/tests/Integration/MigrationIdMapIntegrationTest.php` (new, ~150 lines).

**Steps**:
1. Test 1 — atomic upsert: inside a `transactional()` block, perform an upsert and a deliberate failure (`throw new \RuntimeException('rollback')`). Assert the table has zero rows after the rollback.
2. Test 2 — concurrent reads: open two `DBALDatabase` connections to the same SQLite file; perform an upsert on connection A, immediately read on connection B; assert visibility.
3. Test 3 — schema reversibility: `up(); down(); up()` succeeds without warnings.
4. Test 4 — deterministic ordering on tied timestamps: insert two rows with identical `last_imported_at` but different `last_run_id`; assert `walkReverseCreation()` orders by `last_run_id DESC` as the tie-breaker.

**Validation**:
- [ ] All four tests green on SQLite (the default CI driver).
- [ ] Full suite green.

**Edge cases**:
- SQLite vs MySQL `ON CONFLICT` syntax difference: the test asserts SQLite only; document a follow-up test for Postgres + MySQL when those CI drivers come online (not in scope).

## Tests

- **Unit**: T025 — four files.
- **Integration**: T026 — one file, four tests.
- **Conformance**: WP10.

## Definition of Done

- [ ] All six subtasks complete.
- [ ] All seven FRs cited in code as `@spec FR-xxx`.
- [ ] `composer phpstan` clean.
- [ ] `composer cs-check` clean (run twice).
- [ ] `bin/check-composer-policy` clean.
- [ ] `bin/check-package-layers` clean.
- [ ] `bin/audit-dead-code` clean.
- [ ] `./vendor/bin/phpunit` full-suite green.
- [ ] WP01's `SourceId` stub is replaced (the `\LogicException` is gone; `hash()` returns sha256).
- [ ] `MigrationIdMap` uses `DatabaseInterface`, not raw PDO (`.claude/rules/entity-storage-invariant.md`).
- [ ] All new public symbols carry `@api`.
- [ ] No `psr/log` imports.
- [ ] `CanonicalForm::encode()` uses `JSON_THROW_ON_ERROR` (CLAUDE.md gotcha on json symmetry).

## Risks

- **R1 — Hash drift between v1 and any future v2 canonical form**: lock the canonical form via `CanonicalForm` and its tests. Document that any change requires a charter amendment + migration of existing id-map rows.
- **R2 — Clock collisions on `last_imported_at`** (research §8 R4): mitigated by including `last_run_id` (UUIDv7, timestamp-ordered) as the secondary sort. Covered by T026 Test 4.
- **R3 — Cross-DB upsert syntax**: SQLite is the primary CI target; MySQL + Postgres need follow-up testing. Mitigation: route through `DatabaseInterface::insertOrUpdate()` if available; failing that, switch on `$platform->getName()`.
- **R4 — `WriteResult` mutation**: `WriteResult` is `final readonly` from WP01; `MigrationIdMap::lookupDestination()` constructs a fresh instance per row (no shared mutable state).

## Reviewer guidance

- Check: no raw `\PDO` or `prepare(...)` calls in `MigrationIdMap`. All access goes through `DatabaseInterface`.
- Check: the WP01 stub file is gone (or fully rewritten); `\LogicException('not yet implemented')` no longer appears anywhere.
- Check: `walkReverseCreation()` is a generator (`yield`), not an array-returning method.
- Check: tied-timestamp test asserts `last_run_id` is the deterministic tiebreaker.
- Verify: schema matches spec §8.1 exactly — `migration_id` first in PK, then `source_id_hash`. Both indexes present.
- Verify: `CanonicalForm::encode()` returns identical bytes when called twice with the same input.
- Confirm: `.claude/rules/entity-storage-invariant.md` is honoured — `MigrationIdMap` is not an entity, so direct `DatabaseInterface` access is the allowed path.
