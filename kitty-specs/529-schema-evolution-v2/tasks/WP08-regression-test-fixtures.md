---
work_package_id: WP08
title: Regression test fixtures (#518)
dependencies:
- WP02
- WP03
- WP04
- WP05
- WP06
- WP07
requirement_refs:
- NFR-001
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main.
subtasks:
- T044
- T045
- T046
- T047
- T048
- T049
history:
- date: '2026-05-02'
  note: Initial generation by /spec-kitty.tasks-packages.
authoritative_surface: tests/Integration/Schema
execution_mode: code_change
mission_id: 01KQN41MQD3Y6PG0PES8XX166F
mission_slug: 529-schema-evolution-v2
owned_files:
- tests/Integration/Schema/Diff/**
- tests/Integration/Schema/Compiler/**
- tests/Integration/Schema/Migration/**
tags: [tests, regression, integration, sqlite]
---

# WP08 — Regression test fixtures (#518)

## Objective

Build the regression test surface that closes GitHub issue #518. After this WP:

- Every supported diff shape has a fixture-based integration test that exercises the full chain: `EntityDiffFactory` (or hand-built `CompositeDiff`) → `SqliteCompiler` → `Migrator` → SQLite + ledger.
- Additive cases pass cleanly. Rename-like cases either succeed (explicit `RenameColumn` op) or fail with the expected stable code (drop+add silently coalesced — should NEVER happen, but the test verifies it doesn't).
- Destructive cases fail closed by default and succeed only when `PlanPolicy(allowDestructive: true)` is provided.
- Bundle subtable scenarios cover both: subtable being created from empty AND subtable being added-to.
- `FieldStorage::Data` vs column scenarios verify the K2 invariant from #1257 holds end-to-end.
- Idempotency: applying the same plan twice writes one ledger row + zero SQL on the second apply.

## Context

Read before starting:

- `docs/specs/schema-evolution-v2.md` §12 (test strategy).
- All WP02–WP07 outputs.
- #1257 WP11 test (`tests/Integration/Phase26/Mission1257KernelPathTest.php`) — same kernel-path-lock pattern.

## Subtasks

### T044 — Additive case fixtures

**Purpose:** Add column, add index, add table baseline.

**Cases:**
1. `AddColumn(widgets, archived_at, integer/null)` → SQLite has the column post-apply, ledger row written.
2. `AddIndex(widgets, [archived_at])` → `idx_widgets_archived_at` exists per `PRAGMA index_list`.
3. `AddIndex(widgets, [archived_at, status], unique=true)` → `CREATE UNIQUE INDEX` ran.

**Files:** `tests/Integration/Schema/Compiler/AdditiveOpsTest.php`.

### T045 — Rename-like case fixtures

**Purpose:** Verify that explicit `RenameColumn` works AND that the system never silently coalesces drop+add into a rename.

**Cases:**
1. Explicit `RenameColumn(widgets, status, state)` on SQLite ≥ 3.25 → column is named `state` post-apply.
2. Explicit `RenameColumn(...)` on SQLite < 3.25 → compiler throws `RENAME_COLUMN_UNSUPPORTED_SQLITE_LT_3_25`.
3. A composite of `DropColumn(widgets, status)` + `AddColumn(widgets, state, …)` with `PlanPolicy(allowDestructive: true)` → BOTH ops execute as separate steps; compiler does NOT coalesce. Test verifies ledger / SQL contains both ops.

**Files:** `tests/Integration/Schema/Compiler/RenameOpsTest.php`.

### T046 — Destructive case fixtures

**Purpose:** Lock the policy gate from WP05.

**Cases:**
1. `DropColumn` with default `PlanPolicy()` → `DESTRUCTIVE_OP_BLOCKED`.
2. `DropColumn` with `PlanPolicy(allowDestructive: true)` → succeeds (or fails on SQLite < 3.35 with the documented version error).
3. `DropIndex` with default policy → `DESTRUCTIVE_OP_BLOCKED`.
4. `AddForeignKey` on SQLite → always fails with `FOREIGN_KEY_UNSUPPORTED_SQLITE_V1` regardless of policy.

**Files:** `tests/Integration/Schema/Compiler/DestructiveOpsTest.php`.

### T047 — Bundle subtable diff fixtures

**Purpose:** Exercise `BundleLevelDiff` and `{base}__{bundle}` naming.

**Cases:**
1. EntityType `group` with bundle `team` and 3 bundle fields → `EntityDiffFactory` produces `EntityLevelDiff` containing a `BundleLevelDiff` with 3 `AddColumn` ops on table `group__team`.
2. Apply via Migrator → `group__team` table contains the 3 columns.
3. Add a 4th bundle field, re-run factory → produces a single `AddColumn` op on `group__team` (idempotent for the existing 3).
4. Bundle id containing reserved `__` separator → `EntityDiffFactory` does not produce a diff for it; or, if it does, the compiler rejects with the K1 guard from #1257.

**Files:** `tests/Integration/Schema/Migration/BundleSubtableDiffTest.php`.

### T048 — `FieldStorage::Data` scenarios

**Purpose:** Lock that `_data`-stored fields produce no column ops; reads/writes go through `_data` per #1257 K2.

**Cases:**
1. EntityType with one column-stored field and one `FieldStorage::Data`-stored field → `EntityDiffFactory` produces a single `AddColumn` op for the column-stored field, zero ops for the `_data` field.
2. After apply: SQLite schema has the column for the column-stored field; the `_data` field is NOT a column. Round-trip: write entity, read back via storage — value persists in `_data`.

**Files:** `tests/Integration/Schema/Migration/FieldStorageDataDiffTest.php`.

### T049 — Idempotency tests

**Purpose:** Apply the same plan twice; second apply is no-op.

**Cases:**
1. First apply: `AddColumn(widgets, archived_at, …)` → column added, ledger row written.
2. Re-run the migrator with the same plan list → `MigrationGraph` sees the migration_id in the ledger and skips it. Zero SQL executed. No new ledger row.
3. Apply with the same `migration_id` but a different `CompositeDiff` → with WP09's checksum guard active, fails with `CHECKSUM_MISMATCH`. (For this WP without WP09 ledger columns yet, document the gap and gate the test on `WP04_LANDED` env.)

**Files:** `tests/Integration/Schema/Migration/IdempotencyTest.php`.

## Definition of Done

- [ ] All 6 subtask test files exist and pass.
- [ ] Tests use `:memory:` SQLite — no filesystem fixtures.
- [ ] Tests pin stable error codes (`DESTRUCTIVE_OP_BLOCKED`, `FOREIGN_KEY_UNSUPPORTED_SQLITE_V1`, `RENAME_COLUMN_UNSUPPORTED_SQLITE_LT_3_25`).
- [ ] Tests exercise the K1 / K2 invariants from mission #1257 are not regressed.
- [ ] PHPStan level 5 clean on test files.

## Risks / Reviewer guidance

- **Don't write tests against private internals.** Each test should run through public APIs: `Migrator::run()`, `SqliteCompiler::compile()`, `EntityDiffFactory::forEntityType()`. Reflection is fine for asserting wiring decisions but should be the exception.
- **Idempotency depends on ledger state.** If WP09 hasn't landed when this WP starts, T049's checksum-mismatch case is skipped (gate via env or PHPUnit `markTestSkipped`).
- **Cross-WP regression discipline:** This is the "don't break #1257" gate. Run the full `tests/Integration/Phase26/Mission1257KernelPathTest.php` suite before merging — if any K1/K2/K3 test breaks, the diff factory or compiler regressed an invariant.
- **No new `final` classes that consumers would need to extend.** Same rule as mission #1257 acceptance #6.
