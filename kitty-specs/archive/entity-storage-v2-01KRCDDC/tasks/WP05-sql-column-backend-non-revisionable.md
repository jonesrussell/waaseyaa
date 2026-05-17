---
work_package_id: WP05
title: sql-column backend (non-revisionable)
dependencies:
- WP01
- WP02
requirement_refs:
- FR-011
- FR-012
- FR-013
- FR-014
- FR-015
- FR-016
planning_base_branch: kitty/mission-entity-storage-v2-01KRCDDC
merge_target_branch: kitty/mission-entity-storage-v2-01KRCDDC
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-entity-storage-v2-01KRCDDC. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-entity-storage-v2-01KRCDDC unless the human explicitly redirects the landing branch.
subtasks:
- T025
- T026
- T027
- T028
- T029
- T030
agent: "claude:opus:reviewer:reviewer"
shell_pid: "425664"
history:
- timestamp: '2026-05-11T23:30:00+00:00'
  actor: claude
  action: wp_prompt_generated
  note: Generated from wps.yaml during tasks_packages step.
authoritative_surface: packages/entity-storage/src/Backend
execution_mode: code_change
owned_files:
- packages/entity-storage/src/Backend/SqlColumnBackend.php
- packages/entity-storage/src/Backend/SqlColumnSchemaBuilder.php
- packages/entity-storage/src/Backend/SqlColumnQueryTranslator.php
- packages/entity-storage/src/Backend/TypeMapping.php
- packages/entity-storage/tests/Integration/SqlColumn/**
tags: []
---

# WP05: sql-column backend (non-revisionable)

## Objective

Deliver the **sql-column backend (non-revisionable)** scope of mission `entity-storage-v2-01KRCDDC` (M-001).

Requirement coverage: `FR-011`, `FR-012`, `FR-012a`, `FR-013`, `FR-014`, `FR-015`, `FR-016`.

## Context

- Mission: `entity-storage-v2-01KRCDDC` (M-001 — Multi-Backend Storage with Revisions).
- Canonical spec: `kitty-specs/entity-storage-v2-01KRCDDC/spec.md` (also at `docs/specs/entity-storage-v2.md`).
- Plan: `kitty-specs/entity-storage-v2-01KRCDDC/plan.md`.
- Research + decisions: `kitty-specs/entity-storage-v2-01KRCDDC/research.md`.
- Data model: `kitty-specs/entity-storage-v2-01KRCDDC/data-model.md`.
- Normative contracts: `kitty-specs/entity-storage-v2-01KRCDDC/contracts/`.
- Charter §5.3 governs every stable-surface symbol introduced here.

### Dependencies
- `WP01` (must be done before this WP begins).
- `WP02` (must be done before this WP begins).

## Subtasks

### T025: Implement SqlColumnBackend

Create `packages/entity-storage/src/Backend/SqlColumnBackend.php` implementing `FieldStorageBackendInterface`.

- `id() = 'sql-column'`.
- `read()`: `SELECT` the field's column for the entity row.
- `write()`: `INSERT OR UPDATE` setting the field's column.
- `delete()`: cascade row delete (entity_storage owns the table).
- `supportsQuery()`: true when the field type maps to a §8.2 column type AND (operator is supported OR field is `indexed()`).

**Files**: `packages/entity-storage/src/Backend/SqlColumnBackend.php` (new, ~250 lines).

### T026: Implement SqlColumnSchemaBuilder

Create `packages/entity-storage/src/Backend/SqlColumnSchemaBuilder.php` using `TypeMapping` (T029-prep).

- Generate primary table: entity keys + one column per `FieldDefinition`.
- Column type per §8.2 (SQLite vs Postgres dialect dispatch).
- Decimal: lossless string in SQLite; `NUMERIC(p, s)` in Postgres.
- `float_vector_<n>` types: reject (must route via `storedIn('vector')`).

**Files**: `packages/entity-storage/src/Backend/SqlColumnSchemaBuilder.php` (new, ~180 lines).

### T027: Materialize indexed() as B-tree indexes

In `SqlColumnSchemaBuilder` (T026): when `FieldDefinition::indexed()` is true, emit `CREATE INDEX <table>_<field>_idx ON <table>(<field>)` after the table creation.

**Files**: extends T026 file.

### T028: Implement read/write/delete via DBAL

In `SqlColumnBackend` (T025): use Doctrine DBAL query builder for all SQL operations. Honor `DBALDatabase::createSqlite()` in tests (in-memory). Postgres-specific behaviors (JSONB, UUID) handled via `TypeMapping`.

**Files**: extends T025 file.

### T029: SqlColumnQueryTranslator + TypeMapping

Create `packages/entity-storage/src/Backend/SqlColumnQueryTranslator.php` and `packages/entity-storage/src/Backend/TypeMapping.php`.

- `TypeMapping::columnTypeFor(string $platform, string $fieldType, ?int $length, ?int $precision): string` — §8.2 lookup.
- `SqlColumnQueryTranslator`: translate `EntityQuery` operators (=, !=, <, <=, >, >=, IN, NOT IN, LIKE, NOT LIKE, IS NULL, IS NOT NULL, contains) into DBAL `WHERE` clauses with LIKE wildcard escaping per project convention.

**Files**: `packages/entity-storage/src/Backend/SqlColumnQueryTranslator.php` (new, ~140 lines), `packages/entity-storage/src/Backend/TypeMapping.php` (new, ~80 lines).

### T030: Conformance + integration tests for sql-column

Create `packages/entity-storage/tests/Integration/SqlColumn/`:

- CRUD round-trip with mixed-type fields (string/int/bool/datetime/json).
- Indexed query verified by EXPLAIN (sqlite EXPLAIN QUERY PLAN includes the index name).
- Decimal lossless round-trip.
- Datetime ISO-8601 storage + retrieval.
- `float_vector_<n>` type rejected at schema-build time.

**Files**: `packages/entity-storage/tests/Integration/SqlColumn/*.php` (new, ~300 lines).

## Test Strategy

- Use PHPUnit 10.5 (project-mandated; **do not** pass `-v` — PHPUnit 10.5 rejects it).
- Unit tests under `packages/<pkg>/tests/Unit/`.
- Integration tests under `packages/<pkg>/tests/Integration/`.
- Contract tests under `packages/<pkg>/tests/Contract/` use `#[CoversNothing]`.
- In-memory storage for tests: `DBALDatabase::createSqlite()` (project gotcha — DBAL fetch mode is `fetchAssociative()`).
- Mock final classes with real instances + temp dirs (PHPUnit `createMock()` fails on `final class`).
- Log assertions: capture `Waaseyaa\Foundation\Log\LoggerInterface` via a recording fake; do not use `psr/log`.

## Definition of Done

- All subtasks (6) complete: T025, T026, T027, T028, T029, T030.
- All requirement refs covered by tests: FR-011, FR-012, FR-012a, FR-013, FR-014, FR-015, FR-016.
- `composer cs-check` clean (run twice with cache cleared if needed per project gotcha).
- `composer phpstan` clean.
- `bin/check-package-layers` clean (no upward edges introduced).
- `bin/audit-dead-code` reports no new findings (mark intentional scaffolding with `@api`).
- `bin/check-composer-policy` clean (no `@dev`, no wildcard internal constraints, `self.version` only in root, internal `waaseyaa/*` constraints equal `^<current-tag>`).

## Risks

- **Type mapping mismatches between SQLite and Postgres**. Mitigation: test against both via DBAL platform abstractions; encode decimal as lossless string in SQLite.
- General: stale specs lead to bad code (CLAUDE.md gotcha). When this WP changes behavior, update the relevant `docs/specs/` file in the same PR.

## Reviewer Guidance

- Verify all new public symbols carry `@api` annotations (charter §5.3 stable surface).
- Verify no upward layer imports: `bin/check-package-layers`.
- Verify no service-locator patterns; all dependencies injected via constructor.
- Verify no `psr/log` use; only `Waaseyaa\Foundation\Log\LoggerInterface`.
- Verify no `Illuminate\*` imports; we use Symfony + Doctrine.
- Verify scope: nothing from spec §1.2 / §2.2 non-goals leaks in (moderation, per-field translation, revision admin UI, vector impl, remote backend, cross-backend joins, auto-pruning, listing UI, mass migrations).

## Implementation Command

```bash
spec-kitty agent action implement WP05 --agent sonnet
```

## Review Command

```bash
spec-kitty agent action review WP05 --agent opus
```

Per mission agent assignments (mission.json): implementer = `sonnet`, reviewer = `opus`. Escalation target = `opus-as-implementer` after N=2 rejections.

## Activity Log

- 2026-05-12T02:25:58Z – claude:sonnet:implementer:implementer – shell_pid=423303 – Started implementation via action command
- 2026-05-12T02:36:17Z – claude:sonnet:implementer:implementer – shell_pid=423303 – Ready for review: sql-column backend non-revisionable (T025-T030). Commit feea8c2b3. PHPStan OK, 411/411 tests pass (14 new), CS clean, layers OK, composer-policy OK.
- 2026-05-12T02:36:45Z – claude:opus:reviewer:reviewer – shell_pid=425664 – Started review via action command
- 2026-05-12T02:39:44Z – claude:opus:reviewer:reviewer – shell_pid=425664 – Cycle 1 approved: sql-column backend, §8.2 conformance verified, WP03 byte-identity gate intact, float_vector rejected at 3 layers
