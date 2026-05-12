---
work_package_id: WP05
title: sql-column backend translation storage
dependencies:
- WP01
- WP02
- WP03
- WP04
requirement_refs:
- FR-026
- FR-027
- FR-028
- FR-029
- FR-030
- FR-031
- FR-032
- NFR-001
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T021
- T022
- T023
- T024
- T025
- T026
history: []
authoritative_surface: packages/entity-storage/
execution_mode: code_change
owned_files:
- packages/entity-storage/src/Backend/SqlColumnBackend*.php
- packages/entity-storage/src/Hydration/SqlColumnTranslationHydrator.php
- packages/entity-storage/src/Schema/TranslationSchemaHandler.php
- packages/entity-storage/tests/Backend/SqlColumn*
- packages/entity-storage/tests/Hydration/SqlColumnTranslation*
tags: []
agent: "claude:opus:waaseyaa-implementer:implementer"
shell_pid: "542078"
---

# WP05 — sql-column backend translation storage

## Objective

Ship sql-column backend support for translatable entity types: sibling `<table>__translation` table keyed on `(entity_id, langcode)`, partition fields into primary vs translation tables by `FieldDefinition::isTranslatable()`, left-join hydrator, separate UPDATE/INSERT paths per table, multi-cardinality field-table shape variations.

## Context

- **Spec:** [`../spec.md`](../spec.md) §3.5 (FR-026..FR-032)
- **Data model:** [`../data-model.md`](../data-model.md) "Storage (SQL)" → "sql-column backend"
- **Dependency:** WP04 introduces the schema-sync delegation pattern this WP plugs into via `TranslationSchemaHandler`.

## Subtasks

### T021 — `TranslationSchemaHandler` for sql-column

**Steps:**

1. Create `packages/entity-storage/src/Schema/TranslationSchemaHandler.php`:
   - Class accepts `EntityType` + `DatabaseInterface` (or DBAL Connection).
   - `sync(EntityType $type): void` allocates `<table>__translation` if absent, with columns: `entity_id`, `langcode`, plus one column per translatable field.
   - PK: `(entity_id, langcode)`. FK to `<table>(entity_id) ON DELETE CASCADE`.
2. Register this handler with `EntitySchemaSync`'s handler list (WP04 introduces the delegation point).

**Files:** `packages/entity-storage/src/Schema/TranslationSchemaHandler.php` (new, ~120 lines).

### T022 — Schema generation: partition fields

**Steps:**

1. In `TranslationSchemaHandler::sync()`:
   - Walk `$type->getFieldDefinitions()`.
   - Partition by `FieldDefinition::isTranslatable()`.
   - Translatable columns → translation table.
   - Non-translatable columns → primary table (existing logic; this handler does not touch them).
2. Verify schema sync is idempotent: running it twice produces the same shape (no ALTER TABLE on second invocation).

**Files:** Same file as T021 (~40 lines added).

### T023 — `SqlColumnTranslationHydrator` (left-join read)

**Steps:**

1. Create `packages/entity-storage/src/Hydration/SqlColumnTranslationHydrator.php`:
   - Reads ONE entity by id, returning all extant translations as a map.
   - Single query:
     ```sql
     SELECT pri.*, t.langcode AS _t_langcode, t.<translatable_columns>
     FROM <table>__translation t
     INNER JOIN <table> pri ON pri.entity_id = t.entity_id
     WHERE pri.entity_id = ?
     ORDER BY CASE WHEN t.langcode = pri.default_langcode THEN 0 ELSE 1 END, t.langcode
     ```
   - Materializes one entity per row; primary table cols shared via reference, translation cols per row.
2. Hand off to `TranslatableEntityTrait::_setTranslationData($map, $activeLangcode)`.

**Files:** `packages/entity-storage/src/Hydration/SqlColumnTranslationHydrator.php` (new, ~150 lines).

### T024 — `SqlColumnBackend` translation write

**Steps:**

1. Open the existing sql-column backend (find via `grep -l 'sql-column\|SqlColumn' packages/entity-storage/src/Backend/`).
2. Dispatch each field write by `isTranslatable()`:
   - Translatable field → UPDATE `<table>__translation` WHERE `entity_id = ? AND langcode = ?`.
   - Non-translatable field → UPDATE `<table>` WHERE `entity_id = ?`.
3. INSERT path: when persisting a new translatable entity:
   - INSERT into `<table>` with non-translatable fields + `default_langcode`.
   - INSERT into `<table>__translation` for the default langcode AND for any non-default translation pre-staged (e.g., via `addTranslation()` before first save).
4. DELETE-translation path: when `_takePendingTranslationDeletions()` returns langcodes, issue DELETE on `<table>__translation` for each `(entity_id, langcode)` pair.

**Files:** sql-column backend file (~150 lines added).

### T025 — Multi-cardinality field tables

**Steps:**

1. Schema rule (FR-032):
   - Non-translatable multi-cardinality fields: existing shape `(entity_id, delta)`.
   - Translatable multi-cardinality fields: `(entity_id, langcode, delta)`.
2. FK behaviour:
   - Non-translatable multi-table FK: → `<table>(entity_id)`.
   - Translatable multi-table FK: → `<table>__translation(entity_id, langcode)` (composite FK).
3. Generate ALTER TABLE for existing field-table that flips translatable → out of scope this mission (consumer migration ships separately). Document the policy.

**Files:** TranslationSchemaHandler (~50 lines added).

### T026 — Integration tests for sql-column translatable storage

**Steps:**

1. Create `packages/entity-storage/tests/Backend/SqlColumnTranslatableTest.php`:
   - In-memory SQLite via `DBALDatabase::createSqlite()`.
   - Test: schema sync creates `<table>` and `<table>__translation`.
   - Test: INSERT new translatable entity → 1 row in `<table>`, 1 row in `<table>__translation` (default langcode).
   - Test: addTranslation('oj') + save → 2 rows in `<table>__translation`, 1 row in `<table>`.
   - Test: read translatable field from 'oj' translation reads from translation table; read non-translatable field reads from primary table; both work.
   - Test: write translatable field on 'en' translation does NOT touch primary table.
   - Test: write non-translatable field on 'oj' translation does NOT touch translation table (writes primary only).
   - Test: removeTranslation('oj') deletes 'oj' row from translation table; primary row untouched.
   - Test: hydrator returns all translations in one query (query-count assertion preparation for WP10).

2. Multi-cardinality coverage:
   - Translatable multi-field `notes` → `<table>__notes` with `(entity_id, langcode, delta)` PK.
   - Non-translatable multi-field `tags` → `<table>__tags` with `(entity_id, delta)` PK.

3. NFR-001 baseline check: non-translatable entity types load at baseline latency.

**Files:** ~400 lines of tests.

## Definition of Done

- [ ] `TranslationSchemaHandler` allocates `<table>__translation` with correct shape.
- [ ] Hydrator returns all translations via single INNER JOIN.
- [ ] Read/write paths route translatable vs non-translatable fields to correct tables.
- [ ] Multi-cardinality field-table shapes correct (FR-032).
- [ ] Integration tests pass.
- [ ] No regression on non-translatable types (NFR-001).
- [ ] `composer phpstan`, `composer cs-check`, `bin/check-package-layers` green.

## Risks

| Risk | Mitigation |
|---|---|
| Composite FK constraints not supported by SQLite (they're parsed but enforcement varies). | Document; rely on application-level UnitOfWork ordering for in-memory testing. Production MySQL/PostgreSQL honour composite FKs. |
| Schema sync idempotency: running twice should not ALTER. | Compare current vs target schema via DBAL Schema Comparator; emit no DDL when identical. |
| Field column type derivation (translatable column types must match what's currently emitted on the primary table for non-translatable shape). | Reuse the existing column-type resolver from `SqlSchemaHandler`. Don't duplicate the type map. |
| Race with WP04 on `EntitySchemaSync`. | WP04 owns `EntitySchemaSync.php` and introduces the handler delegation point. WP05 only adds NEW classes under `packages/entity-storage/src/Schema/`. No conflict. |

## Reviewer guidance

- Check the INNER JOIN ordering: default langcode first (CASE expression).
- Check that schema sync is idempotent on second invocation.
- Check that translatable multi-cardinality field tables have the composite FK.
- Check that no SQL injection paths exist in identifier interpolation (use DBAL identifier quoting consistently).

## Implementation command

```bash
spec-kitty agent action implement WP05 --agent <name>
```

## Activity Log

- 2026-05-12T22:48:18Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=542078 – Started implementation via action command
