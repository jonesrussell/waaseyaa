---
work_package_id: WP04
title: sql-blob backend translation storage + schema-sync extension
dependencies:
- WP01
- WP02
requirement_refs:
- FR-020
- FR-021
- FR-022
- FR-023
- FR-024
- FR-025
- NFR-001
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T015
- T016
- T017
- T018
- T019
- T020
history: []
authoritative_surface: packages/entity-storage/
execution_mode: code_change
owned_files:
- packages/entity-storage/src/Backend/SqlBlobBackend.php
- packages/entity-storage/src/Backend/SqlBlobBackend*.php
- packages/entity-storage/src/EntitySchemaSync.php
- packages/entity-storage/src/SqlSchemaHandler.php
- packages/entity-storage/tests/Backend/SqlBlob*
tags: []
agent: "claude:sonnet:waaseyaa-implementer:implementer"
shell_pid: "534419"
---

# WP04 — sql-blob backend translation storage + schema-sync extension

## Objective

Ship sql-blob backend support for translatable entity types: PK widening to `(entity_id, langcode)`, `default_langcode` column, per-langcode `_data` blob, UUID partial-unique-index. Non-translatable field values stored once on the default-langcode row, with single-step fallback for non-default reads. Establish the schema-sync infrastructure WP05 extends.

## Context

- **Spec:** [`../spec.md`](../spec.md) §3.4 (FR-020..FR-025), §4 (NFR-001)
- **Data model:** [`../data-model.md`](../data-model.md) "Storage (SQL)" → "sql-blob backend"
- **Existing code:** `packages/entity-storage/src/EntitySchemaSync.php:31` reads `EntityType::isTranslatable()` already (the tombstone read). `packages/entity-storage/src/SqlSchemaHandler.php` owns the actual CREATE/ALTER SQL generation.

## Subtasks

### T015 — `EntitySchemaSync`: widen PK on translatable sql-blob types

**Steps:**

1. Open `packages/entity-storage/src/EntitySchemaSync.php`. The existing `isTranslatable()` check at line 31 currently has no branch body.
2. When `$entityType->isTranslatable() === true` AND the backend is `sql-blob`:
   - Compose the primary key as `(entity_id, langcode)` instead of `(entity_id)`.
   - Delegate to `SqlSchemaHandler` for the actual DDL.
3. Add a helper method `private function isSqlBlobBackend(EntityType $type): bool` that resolves the entity's backend id (from `EntityType::primaryStorageBackend` or framework default).

**Files:** `packages/entity-storage/src/EntitySchemaSync.php` (modify, ~30 lines).

### T016 — `default_langcode` column on sql-blob translatable types

**Steps:**

1. Extend `SqlSchemaHandler` to allocate `default_langcode VARCHAR(12) NOT NULL` on sql-blob translatable types. The column lives on every row.
2. The `langcode` column already exists on the schema (used to be a query filter); now it's part of the primary key.

**Files:** `packages/entity-storage/src/SqlSchemaHandler.php` (modify, ~20 lines).

### T017 — UUID partial-unique-index for sql-blob translatable

**Purpose:** A single entity (one `entity_id`) has multiple rows across langcodes. All rows for the same entity share the same UUID. Uniqueness must be enforced on `(uuid)` but only for the default-langcode row.

**Steps:**

1. Generate a partial unique index in SqlSchemaHandler:
   ```sql
   CREATE UNIQUE INDEX <table>_uuid_default
       ON <table>(uuid) WHERE langcode = default_langcode;
   ```
2. For SQLite this is straightforward; for MySQL/MariaDB <8.0.13 partial indexes aren't supported — emit a warning and use a regular index plus an application-level check. For modern MySQL/MariaDB and PostgreSQL, partial unique indexes are supported.
3. Document the partial-index requirement in `SqlSchemaHandler`'s docblock.

**Files:** `packages/entity-storage/src/SqlSchemaHandler.php` (modify, ~25 lines).

### T018 — `SqlBlobBackend` translation read

**Purpose:** Read translation rows; non-translatable fields fall through to default-langcode row.

**Steps:**

1. Open `packages/entity-storage/src/Backend/SqlBlobBackend.php` (or wherever sql-blob runtime read happens).
2. For translatable entity types: load ALL rows for `entity_id`, build a `translationData` map keyed by langcode, hand it to the hydrated entity via `$entity->_setTranslationData($map, $activeLangcode)` (the trait helper from WP01).
3. For non-translatable field reads from a non-default-langcode row: single-step fallback to the default-langcode row's `_data` blob (FR-022).
4. Active-langcode resolution at load time: default to `default_langcode` unless caller specifies otherwise.

**Files:** `packages/entity-storage/src/Backend/SqlBlobBackend*.php` (modify, ~100 lines).

### T019 — `SqlBlobBackend` translation write

**Purpose:** Write per-langcode rows; non-translatable writes route to default-langcode row only.

**Steps:**

1. Field-write entry point in the backend dispatches based on `FieldDefinition::isTranslatable()`:
   - Translatable field: write to `_data` blob of the row keyed by `(entity_id, activeLangcode)`.
   - Non-translatable field: write to `_data` blob of the row keyed by `(entity_id, default_langcode)`.
2. INSERT path:
   - New translatable entity → INSERT row with `(entity_id, default_langcode, _data=non-translatable+translatable, uuid=...)`.
   - `addTranslation($lc)` then save → INSERT new row `(entity_id, $lc, _data=translatable-only, uuid=...)`.
3. UPDATE path:
   - Update default-langcode row: UPDATE its `_data` (merge incoming non-translatable fields onto existing).
   - Update non-default-langcode row: UPDATE its `_data` (only translatable fields).

**Files:** Same as T018 (~80 lines added).

### T020 — Integration tests for sql-blob translatable storage

**Steps:**

1. Create `packages/entity-storage/tests/Backend/SqlBlobTranslatableTest.php`:
   - Use `DBALDatabase::createSqlite()` for in-memory testing.
   - Use the test fixture entity type (WP13 fixture, but inline definition is fine for this WP — the fixture lands later).
   - Test: create entity with default-langcode 'en', save → 1 row in DB with langcode='en', _data contains all fields.
   - Test: addTranslation('oj') + set translatable fields + save → 2 rows in DB, second has _data with only translatable fields.
   - Test: read non-translatable field from 'oj' translation → returns value from 'en' row (fallback).
   - Test: write non-translatable field on 'oj' translation → updates 'en' row's _data, not 'oj' row.
   - Test: removeTranslation('oj') + save → 'oj' row deleted; 'en' row preserved.
   - Test: UUID uniqueness: two entities with same UUID (different entity_id) → INSERT fails; same UUID across translations of same entity → OK.

2. Performance check for NFR-001:
   - Baseline: load a non-translatable entity type pre-mission.
   - Post-mission: load the same type with the new code path active.
   - Assert p95 latency delta ≤ 0%.

**Files:** ~300 lines of tests.

## Definition of Done

- [ ] sql-blob schema for translatable types: PK = `(entity_id, langcode)`, `default_langcode` column, UUID partial-unique-index.
- [ ] Read path returns per-langcode `_data` with non-translatable fallback.
- [ ] Write path routes non-translatable updates to default-langcode row.
- [ ] Integration tests pass on SQLite.
- [ ] Non-translatable types (the existing universe) load + save unchanged (no regression).
- [ ] `composer phpstan`, `composer cs-check`, `bin/check-package-layers` green.

## Risks

| Risk | Mitigation |
|---|---|
| Partial unique index portability (MySQL/MariaDB <8.0.13 unsupported). | Emit version-detect warning; fall back to non-unique index + application-level check. Document in SqlSchemaHandler. |
| UPDATE deltas might over-write _data on the wrong row when caller mixes translatable + non-translatable updates. | Field-by-field dispatch based on `FieldDefinition::isTranslatable()`. Two separate UPDATE statements per save when both buckets have deltas. |
| `_data` JSON merging on UPDATE could clobber unmodified fields. | Read existing `_data`, decode, merge incoming deltas, re-encode, UPDATE (write-back-the-whole-blob pattern). Same as existing single-row flow. |
| NFR-001 regression on non-translatable types if the new code path is taken even when `translatable: false`. | Gate the new code path on `EntityType::isTranslatable()`. Existing code path stays verbatim for non-translatable types. |

## Reviewer guidance

- Check the partial unique index handling across DBs (SQLite OK, modern MySQL OK, older MySQL warning).
- Check that `_data` JSON read-modify-write uses `JSON_THROW_ON_ERROR` consistently (project rule).
- Check that non-translatable entity load latency is unchanged via the baseline test.
- Check FK relationships from related tables (e.g. `<table>__field_X`) still point at `<table>(entity_id)` only — they do not include `langcode` for non-translatable multi-cardinality fields. (Translatable multi-cardinality lives in WP05.)
- Check the lane worktree has `composer install` run before running gates.

## Implementation command

```bash
spec-kitty agent action implement WP04 --agent <name>
```

## Activity Log

- 2026-05-12T22:24:10Z – claude:sonnet:waaseyaa-implementer:implementer – shell_pid=534419 – Started implementation via action command
- 2026-05-12T22:43:44Z – claude:sonnet:waaseyaa-implementer:implementer – shell_pid=534419 – sql-blob translation: PK widening, default_langcode column, per-langcode _data, non-translatable fallback, UUID partial unique index
