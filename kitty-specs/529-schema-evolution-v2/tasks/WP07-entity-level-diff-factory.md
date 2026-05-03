---
work_package_id: WP07
title: EntityLevelDiff factory in entity-storage
dependencies:
- WP02
- WP03
requirement_refs:
- FR-001
- C-007
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T038
- T039
- T040
- T041
- T042
- T043
history:
- date: '2026-05-02'
  note: Initial generation by /spec-kitty.tasks-packages.
authoritative_surface: packages/entity-storage/src/Schema
execution_mode: code_change
mission_id: 01KQN41MQD3Y6PG0PES8XX166F
mission_slug: 529-schema-evolution-v2
owned_files:
- packages/entity-storage/src/Schema/**
- packages/entity-storage/tests/Unit/Schema/**
tags:
- entity-storage
- factory
- entity-level-diff
---

# WP07 — `EntityLevelDiff` factory in entity-storage

## Objective

Land the entity-scoped diff producer in `waaseyaa/entity-storage` (Layer 1). Per Q7's ratified decision, the foundation layer holds the **value types** (atomic ops, `CompositeDiff`); the entity-storage layer holds the **factories** that turn an `EntityType` + `FieldDefinitionRegistry` into a concrete `EntityLevelDiff`.

After this WP:

- `EntityLevelDiff` is a readonly wrapper bundling `entity_type_id` + child `CompositeDiff` (per spec §3.4).
- `BundleLevelDiff` covers single-bundle subtable scope.
- A factory `EntityDiffFactory` consumes `EntityType` + current schema state + new schema state and produces an `EntityLevelDiff`.
- The factory delegates column-spec semantics to `SqlSchemaHandler::deriveColumnSpec()` so there is exactly one mapping from field type → column.
- Subtable handling matches `bundle-scoped-storage.md`: `{base}__{bundle}` naming via `SqlSchemaHandler::resolveSubtableName()`.

## Context

Read before starting:

- `docs/specs/schema-evolution-v2.md` §3.4 (composite/scoped), §15 Q7.
- `docs/specs/bundle-scoped-storage.md` for subtable naming + bundle scope.
- `docs/specs/entity-system.md` for `EntityType` shape + `FieldDefinitionRegistryInterface`.
- WP02 output: `Schema/Diff/CompositeDiff.php`, `AddColumn`, `AddIndex`, etc.
- Existing `packages/entity-storage/src/SqlSchemaHandler.php` — the canonical place for column derivation and subtable naming.

## Subtasks

### T038 — `EntityLevelDiff` readonly wrapper

**Purpose:** Bind an entity type id to its `CompositeDiff` for traceability and verify-mode metadata.

**Steps:**
1. Create `packages/entity-storage/src/Schema/EntityLevelDiff.php` as `final readonly class`.
2. Fields: `entityTypeId: string`, `composite: CompositeDiff`.
3. `toCanonical(): array` returns `['entity_type_id' => …, 'composite' => $this->composite->toCanonical()]`.
4. `checksum()` delegates to `composite` — entity-type-id is metadata for traceability, not part of structural identity.

**Files:** `Schema/EntityLevelDiff.php`.

### T039 — `BundleLevelDiff` (subset of entity scope)

**Purpose:** Scope diffs to a single `{base}__{bundle}` subtable when only that bundle's schema changed.

**Steps:**
1. `Schema/BundleLevelDiff.php`: `final readonly class` with `entityTypeId`, `bundleId`, `composite`.
2. `subtableName(): string` calls `SqlSchemaHandler::resolveSubtableName($baseTable, $bundleId)` (the centralized helper from mission #1257 WP08).
3. Document that BundleLevelDiff ops touch only the subtable; base-table touches require an EntityLevelDiff that wraps both BundleLevelDiff + base-table ops.

**Files:** `Schema/BundleLevelDiff.php`.

### T040 — `EntityDiffFactory`

**Purpose:** Compute an `EntityLevelDiff` by comparing the registered field definitions against a snapshot of current schema state.

**Steps:**
1. Create `Schema/EntityDiffFactory.php` as `final readonly class`.
2. Constructor: `EntityDiffFactory(SqlSchemaHandler $schemaHandler, FieldDefinitionRegistryInterface $registry)`.
3. Method: `forEntityType(EntityTypeInterface $type, SchemaSnapshot $current): EntityLevelDiff`.
4. Algorithm:
   - For each registered core field: if not in `$current`, emit `AddColumn(table, column, deriveColumnSpec(field))`.
   - For each registered bundle: if subtable missing, emit `AddColumn` for each bundle field on the subtable (via `BundleLevelDiff`).
   - Existing columns whose spec changed: emit `AlterColumn` (the compiler will reject on SQLite; the factory just produces the diff).
   - Removed columns: emit `DropColumn` (compiler gates apply).
5. Use `FieldStorage::Data` semantics from #1257 WP09 — `_data`-stored fields do NOT produce columns.

**Files:** `Schema/EntityDiffFactory.php`, `Schema/SchemaSnapshot.php` (a small DTO carrying `tables: array<string, list<ColumnSpec>>`).

### T041 — Use `deriveColumnSpec()` as single source of truth

**Purpose:** No duplicate field-type → column mapping lives in this WP.

**Steps:**
1. The factory calls `SqlSchemaHandler::deriveColumnSpec($fieldDefinition)` (already public per #1305) to get the canonical `ColumnSpec`.
2. Translate that into the foundation `Schema\Diff\ColumnSpec` value type. The translation is direct (same fields), but the entity-storage factory is the only place that touches both shapes — foundation never imports from entity-storage.

**Files:** modify `Schema/EntityDiffFactory.php` to call `deriveColumnSpec()`.

### T042 — Subtable handling per `bundle-scoped-storage.md`

**Purpose:** `{base}__{bundle}` naming + bundle scope rules.

**Steps:**
1. The factory iterates `EntityType::getBundleEntityType()` if non-null and consults the field registry for bundle-scoped fields.
2. Each bundle's diff goes into a `BundleLevelDiff` whose `subtableName()` matches `SqlSchemaHandler::resolveSubtableName($baseTable, $bundleId)`.
3. Empty subtables (no bundle fields registered) produce no `BundleLevelDiff` — the factory does NOT pre-create empty subtables.

**Files:** modify `Schema/EntityDiffFactory.php`.

### T043 — Unit tests for factory

**Cases:**
1. EntityType with 2 core fields, current snapshot empty → `EntityLevelDiff` containing 2 `AddColumn` ops + (assert nothing for `_data` since base table is created elsewhere; this WP focuses on field columns).
2. EntityType with bundle entity type, one bundle has 3 fields, snapshot empty → `EntityLevelDiff` containing a `BundleLevelDiff` with 3 `AddColumn` ops on `{base}__{bundle}`.
3. Field changed type from `string` to `text` → factory emits `AlterColumn` (compiler gates apply later).
4. Field removed from registry → factory emits `DropColumn` (compiler gates apply).
5. `FieldStorage::Data` field → factory emits NO column op for that field.

**Files:** `packages/entity-storage/tests/Unit/Schema/EntityDiffFactoryTest.php`.

## Definition of Done

- [ ] `EntityLevelDiff`, `BundleLevelDiff`, `EntityDiffFactory`, `SchemaSnapshot` exist in `packages/entity-storage/src/Schema/`.
- [ ] Factory uses `SqlSchemaHandler::deriveColumnSpec()` as the only column-spec source.
- [ ] Subtable naming uses `SqlSchemaHandler::resolveSubtableName()`.
- [ ] `bin/check-package-layers` clean — entity-storage may import foundation, foundation does not import entity-storage.
- [ ] Unit tests cover all subtask cases.
- [ ] PHPStan level 5 clean.

## Risks / Reviewer guidance

- **Layer direction:** entity-storage → foundation is fine (downward). Foundation must not gain any imports from this WP. Reviewer should confirm `bin/check-package-layers` covers this.
- **`FieldStorage::Data` discipline:** From #1257 WP09, `_data`-stored fields are NOT materialized as columns. The factory must respect this. A regression here re-opens the K2 invariant.
- **Snapshot source is out of scope:** This WP defines `SchemaSnapshot` as a DTO. Producing a snapshot from a live DB is a verify-mode concern (WP10). Tests pass snapshots constructed by hand.
- **Don't over-engineer rename detection:** Per §3.3, rename is never inferred. The factory does NOT try to detect "looks like a rename" patterns. Drop + add. Operators who want a rename author it explicitly with `RenameColumn`.

## Activity Log

- 2026-05-03T01:07:03Z – unknown – Moved to in_progress
- 2026-05-03T01:17:26Z – unknown – Moved to for_review
- 2026-05-03T01:17:28Z – unknown – Moved to approved
