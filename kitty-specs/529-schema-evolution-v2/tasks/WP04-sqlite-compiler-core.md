---
work_package_id: WP04
title: SQLite compiler core (additive ops only)
dependencies:
- WP02
requirement_refs:
- FR-001
- C-005
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main.
subtasks:
- T017
- T018
- T019
- T020
- T021
- T022
- T023
history:
- date: '2026-05-02'
  note: Initial generation by /spec-kitty.tasks-packages.
authoritative_surface: packages/foundation/src/Schema/Compiler/Sqlite
execution_mode: code_change
mission_id: 01KQN41MQD3Y6PG0PES8XX166F
mission_slug: 529-schema-evolution-v2
owned_files:
- packages/foundation/src/Schema/Compiler/Sqlite/**
- packages/foundation/tests/Unit/Schema/Compiler/Sqlite/**
tags: [foundation, compiler, sqlite, additive]
---

# WP04 — SQLite compiler core (additive ops only)

## Objective

Compile `CompositeDiff` into a `CompiledMigrationPlan` for SQLite, **additive operations only**. After this WP:

- A pure function `SqliteCompiler::compile(CompositeDiff): CompiledMigrationPlan` exists.
- Supported ops: `AddColumn`, `AddIndex`, `RenameColumn`, `RenameTable`. Index creation includes composite columns; rename ops require SQLite ≥ 3.25 (assert at compile time, fail with stable code if older).
- `CompiledMigrationPlan` is an immutable ordered list of narrow DTOs (`ExecuteStatement`, `CreateTable`, `AlterTableAddColumn`, etc.) — no caller-supplied SQL strings reach this layer.
- Determinism is provable: same `CompositeDiff` + same compiler version ⇒ byte-identical step sequence (golden tests).
- Validation gates and capability matrix come in WP05. This WP focuses on the additive happy path.

## Context

Read before starting:

- `docs/specs/schema-evolution-v2.md` §5 (compiler contract), §15 Q5 (AlterColumn rejection — handled in WP05, not here).
- WP02: `Schema/Diff/` op types. The compiler reads them; it does not modify them.
- `packages/foundation/src/Database/` for DBAL platform conventions if any (the compiler emits raw SQLite SQL; DBAL Platform integration is optional and out of scope for v1).

## Subtasks

### T017 — `SqlitePlatform` compiler entry + capability declaration

**Purpose:** A single typed entry point: `SqliteCompiler::compile()`. Capabilities are explicit.

**Steps:**
1. Create `packages/foundation/src/Schema/Compiler/Sqlite/SqliteCompiler.php` as `final readonly class`.
2. Constructor takes `SqliteCapabilities` (or accept via static factory `for(string $sqliteVersion)`).
3. Public method: `compile(CompositeDiff $diff): CompiledMigrationPlan`.
4. `SqliteCapabilities` enum-or-DTO declares: `supportsRenameColumn` (≥ 3.25), `foreignKeysEnabled` (boolean — informational), `version` (string).
5. The compiler dispatches per `OpKind` via `match`. Unknown / unsupported kinds throw a structured exception (caught and reframed by WP05's gates).

**Files:** `Schema/Compiler/Sqlite/SqliteCompiler.php`, `SqliteCapabilities.php`.

### T018 — `AddColumn` → SQL generator

**Purpose:** Emit `ALTER TABLE "<table>" ADD COLUMN "<col>" <type-sql>` for additive column ops.

**Steps:**
1. Map `ColumnSpec.type` → SQLite affinity (`integer`, `text`, `real`, `blob`, `numeric`). The mapping must align with `SqlSchemaHandler::deriveColumnSpec()` semantically — but the compiler implements its own table; do not import entity-storage.
2. Emit `NOT NULL` when `spec.nullable === false`. Emit `DEFAULT <literal>` when `spec.default !== null`. Quote identifiers with double quotes per SQLite convention.
3. Output a single `AlterTableAddColumn` step DTO (not a raw SQL string in the public DTO — the SQL goes inside the DTO, but the DTO is what the executor sees).

**Files:** `Schema/Compiler/Sqlite/Translator/AddColumnTranslator.php`, `Schema/Compiler/Sqlite/Step/AlterTableAddColumn.php`.

### T019 — `AddIndex` → SQL generator

**Purpose:** Emit `CREATE [UNIQUE] INDEX "<name>" ON "<table>" ("<col1>", "<col2>", …)`.

**Steps:**
1. If `op.name` is null, derive a deterministic name: `idx_<table>_<col1>__<col2>` (truncate at SQLite's 63-char identifier limit; document the truncation in the docblock).
2. `unique=true` emits `CREATE UNIQUE INDEX`.
3. Output a `CreateIndex` step DTO.

**Files:** `Schema/Compiler/Sqlite/Translator/AddIndexTranslator.php`, `Step/CreateIndex.php`.

### T020 — `RenameColumn` → SQL generator (SQLite ≥ 3.25)

**Purpose:** Emit `ALTER TABLE "<table>" RENAME COLUMN "<from>" TO "<to>"`.

**Steps:**
1. At compile time, check `SqliteCapabilities::supportsRenameColumn`. If false, throw `RenameColumnUnsupportedException` with a stable code (`RENAME_COLUMN_UNSUPPORTED_SQLITE_LT_3_25`).
2. Output an `ExecuteStatement` step DTO (no specialised type needed).

**Files:** `Translator/RenameColumnTranslator.php`, optionally `Step/ExecuteStatement.php` (shared).

### T021 — `RenameTable` → SQL generator

**Purpose:** Emit `ALTER TABLE "<from>" RENAME TO "<to>"`.

**Steps:**
1. Always supported on SQLite ≥ 3.0. Output `ExecuteStatement`.

**Files:** `Translator/RenameTableTranslator.php`.

### T022 — `CompiledMigrationPlan` output type

**Purpose:** The immutable, ordered DTO list returned by the compiler.

**Steps:**
1. `Schema/Compiler/CompiledMigrationPlan.php` (`final readonly class`) — note: lives in the parent `Compiler/` directory, not the `Sqlite/` subdir, because it's platform-neutral.
2. Holds `list<CompiledStep>` where `CompiledStep` is a sealed-style interface implemented by `ExecuteStatement`, `AlterTableAddColumn`, `CreateIndex`, `CreateTable` (future), etc.
3. `toCanonical()` / `canonicalJson()` / `diffHash()` (the Q2 SHA-256 over canonical compiled-plan JSON — the value WP09 stores in `migration_repository.diff_hash`).

**Files:** `Schema/Compiler/CompiledMigrationPlan.php`, `Schema/Compiler/CompiledStep.php`.

### T023 — Determinism golden tests

**Purpose:** Lock the byte-identical guarantee from §5.2.

**Cases:**
1. Compile a fixture `CompositeDiff([AddColumn(table=widgets, col=archived_at, spec=integer/null)])` against SQLite 3.40 capabilities → assert exact step DTO list + golden `diff_hash`.
2. Compile a multi-op composite (add column, add index, rename column) → assert step ordering matches input op ordering.
3. Compile twice in two separate processes → identical `diff_hash`.
4. Compile with a different SQLite version (3.24, no rename support) and a `RenameColumn` op → expect compile-time exception with code `RENAME_COLUMN_UNSUPPORTED_SQLITE_LT_3_25`.

**Files:** `tests/Unit/Schema/Compiler/Sqlite/SqliteCompilerTest.php`, fixtures in `tests/Unit/Schema/Compiler/Sqlite/Fixtures/`.

## Definition of Done

- [ ] `SqliteCompiler::compile()` exists and handles `AddColumn`, `AddIndex`, `RenameColumn` (with capability gate), `RenameTable`.
- [ ] `CompiledMigrationPlan` produces a stable `diff_hash` via canonical JSON.
- [ ] `bin/check-package-layers`, `bin/check-composer-policy`, PHPStan level 5 clean.
- [ ] Golden tests cover happy paths + the rename-version capability gate.
- [ ] Unsupported ops (`AlterColumn`, `DropColumn`, `DropIndex`, `AddForeignKey`, `DropForeignKey`) cause the compiler to throw a "not implemented in this WP" exception that WP05 will replace with the proper validation gate path.

## Risks / Reviewer guidance

- **Don't import DBAL `Schema` here.** The compiler emits SQL DTOs directly; DBAL integration is a separate ADR. Importing DBAL silently couples Phase 3 to a heavy dependency we don't yet need.
- **Identifier quoting:** SQLite accepts both backtick and double-quote, but double-quote is SQL-standard and what we'll need for cross-DB later. Use double quotes consistently.
- **Determinism > terseness:** Don't shortcut the canonical-JSON path. Two compiler runs producing different `diff_hash` for the same input would silently break verify mode in WP09 / WP10.
- **No DB connection in this WP.** Compiler is a pure function: in = `CompositeDiff`, out = `CompiledMigrationPlan`. Any test that opens a SQLite handle here is a smell — that belongs to WP08 / WP06.
