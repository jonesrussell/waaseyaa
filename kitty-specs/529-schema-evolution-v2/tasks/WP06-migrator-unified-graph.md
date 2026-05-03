---
work_package_id: WP06
title: Migrator integration (legacy + v2 unified graph)
dependencies:
- WP03
- WP04
- WP05
requirement_refs:
- FR-005
- C-004
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T031
- T032
- T033
- T034
- T035
- T036
- T037
history:
- date: '2026-05-02'
  note: Initial generation by /spec-kitty.tasks-packages.
authoritative_surface: packages/foundation/src/Migration
execution_mode: code_change
mission_id: 01KQN41MQD3Y6PG0PES8XX166F
mission_slug: 529-schema-evolution-v2
owned_files:
- packages/foundation/src/Migration/Migrator.php
- packages/foundation/src/Migration/Dag/**
- packages/foundation/tests/Unit/Migration/Dag/**
- packages/foundation/tests/Integration/Migration/UnifiedGraph/**
tags:
- foundation
- migrator
- dag
- integration
---

# WP06 — Migrator integration (legacy + v2 unified graph)

## Objective

Make `Migrator::run()` deterministic across mixed legacy + v2 migrations. After this WP:

- A single DAG holds all pending units (legacy `Migration` instances + v2 `MigrationInterfaceV2` instances). Edges come from `$after` (legacy) and `dependencies` (v2). Per Q4, the graph is topologically sorted with tie-break `(package ASC, migration ASC)`.
- Cross-kind edges are allowed and respected: a v2 plan can declare a dependency on a legacy migration (and vice versa).
- Cycle detection at boot/migrate time produces a clear `MigrationCycleDetectedException` listing the offending nodes.
- Each batch runs ops in deterministic order; a single batch number wraps the whole apply (no per-kind batch split).
- The Migrator dispatches: legacy nodes through `Migration::up($schemaBuilder)`, v2 nodes through the WP04 compiler + the executor (see §7.1).
- Empty v2 plans (`CompositeDiff::empty()`) write a ledger row but execute zero SQL.

This WP completes the v2 execution path. WP09 (ledger) and WP10 (CLI) plug in next.

## Context

Read before starting:

- `docs/specs/schema-evolution-v2.md` §7 (execution model), §9 (coexistence), §15 Q4.
- WP03 output: `MigrationInterfaceV2`, `MigrationPlan`, dependency semantics.
- WP04 output: `SqliteCompiler::compile()` returning `CompiledMigrationPlan`.
- WP05 output: `PlanPolicy`, validation exceptions.
- Existing `packages/foundation/src/Migration/Migrator.php` — current legacy run loop (`run()` iterates pending migrations in topological package order).

## Subtasks

### T031 — Single DAG ordering algorithm (Q4)

**Purpose:** Produce one ordered list from mixed legacy + v2 nodes.

**Steps:**
1. Create `packages/foundation/src/Migration/Dag/MigrationGraph.php` as `final readonly class`.
2. `MigrationGraph::build(list<Migration|MigrationInterfaceV2> $pending): self` constructs the graph.
3. Each node has: `id` (string, the ledger key), `package`, `kind` (`'legacy' | 'v2'`), `dependencies: list<string>`.
4. `topologicalOrder(): list<MigrationNode>` returns a deterministic order:
   - Standard Kahn's algorithm.
   - Tie-break: when multiple nodes have zero in-degree, sort by `(package ASC, id ASC)` per Q4.

**Files:** `Migration/Dag/MigrationGraph.php`, `Migration/Dag/MigrationNode.php`.

### T032 — Migrator dispatch path for v2

**Purpose:** Route each node to the right execution path.

**Steps:**
1. Modify `Migrator::run()` to consume the topologically-ordered list from `MigrationGraph`.
2. For each node:
   - **Legacy:** existing path — instantiate, call `Migration::up($schemaBuilder)`, then write ledger.
   - **v2:** call `SqliteCompiler::compile($plan->root, $plan->policy ?? new PlanPolicy())`, then execute each `CompiledStep` via DBAL connection.
3. Wrap the entire batch in a single transaction where the platform supports it (SQLite: yes for additive; AlterColumn / DropColumn rebuild flows are out of scope per WP05).
4. Empty plan: write the ledger row, skip execution.

**Files:** modify `Migration/Migrator.php`, add `Migration/Executor/V2PlanExecutor.php`.

### T033 — Cross-kind dependency edges

**Purpose:** A v2 plan declaring `dependencies: ['waaseyaa/groups:001-create-groups']` (a legacy migration name) must wait for that legacy migration. And vice versa via `$after`.

**Steps:**
1. In `MigrationGraph::build()`, resolve dependency strings against the union of (legacy migration names + v2 migration_id strings).
2. Unknown references throw `UnknownDependencyException(code: 'UNKNOWN_DEPENDENCY', dependency: '<string>', source: '<id>')`.
3. Document that legacy `$after` accepts package names (matches today's semantics) AND v2 migration_id strings (new).

**Files:** modify `MigrationGraph.php`, add `UnknownDependencyException.php`.

### T034 — Cycle detection

**Purpose:** Fail loud at boot/migrate time, not silently apply nothing.

**Steps:**
1. Kahn's algorithm naturally detects cycles: if pending nodes remain after the queue empties, those form a cycle.
2. Walk the residual graph to find one concrete cycle (any cycle is enough for the error message).
3. Throw `MigrationCycleDetectedException(code: 'MIGRATION_CYCLE', cycle: list<string>)` with the full ID list of one cycle.
4. Keep the message stable for operator dashboards.

**Files:** add `MigrationCycleDetectedException.php`, modify `MigrationGraph.php`.

### T035 — Batch boundary semantics

**Purpose:** Lock today's "one batch per apply" model under the unified path.

**Steps:**
1. The Migrator computes a single new batch number for the whole `run()` call; every node applied in this run receives that batch number.
2. Mixed kinds within one batch is allowed (and the natural outcome of the unified DAG).
3. Failure mid-batch: rollback per-node SQL transaction; ledger writes for prior nodes in the same batch persist (matches today's semantics — document this explicitly).

**Files:** modify `Migration/Migrator.php`, `Migration/MigrationRepository.php` (no schema change here — that's WP09).

### T036 — Unit tests for ordering + cycle detection

**Cases:**
1. Two legacy migrations, one v2: topological order respects `$after` and `dependencies` strings.
2. Tie-break: two nodes with no deps, packages `waaseyaa/users` and `waaseyaa/groups` → `groups` runs first (ASC).
3. Cross-kind edge: v2 depends on legacy → ordering verified.
4. Cycle: legacy A → v2 B → legacy A → throws `MIGRATION_CYCLE` listing the cycle.
5. Unknown dep: v2 plan declares `dependencies: ['nonexistent']` → throws `UNKNOWN_DEPENDENCY`.
6. Empty v2 plan: ledger row written, zero SQL executed.

**Files:** `tests/Unit/Migration/Dag/MigrationGraphTest.php`, `tests/Unit/Migration/Executor/V2PlanExecutorTest.php`.

### T037 — Integration test: kernel + sqlite, mixed chain

**Purpose:** End-to-end lock in the same spirit as #1257 WP11.

**Steps:**
1. Wire `DBALDatabase::createSqlite(':memory:')` + `Migrator` + a fixture set of two legacy migrations and two v2 plans with cross-kind deps.
2. Call `Migrator::run()`.
3. Introspect SQLite schema (`PRAGMA table_info`); assert tables and columns match the expected post-state.
4. Assert ledger has 4 rows in deterministic batch + order.

**Files:** `tests/Integration/Migration/UnifiedGraph/UnifiedGraphTest.php`.

## Definition of Done

- [ ] `MigrationGraph` produces deterministic topological order with `(package ASC, id ASC)` tie-break.
- [ ] `Migrator::run()` handles both kinds in one batch.
- [ ] Cycle detection + unknown-dependency detection produce stable codes.
- [ ] Empty v2 plans apply as ledger-only.
- [ ] Unit + integration tests pass.
- [ ] `bin/check-package-layers`, `bin/check-composer-policy`, PHPStan level 5 clean.

## Risks / Reviewer guidance

- **Don't break legacy migrations.** Existing `Migration::up()` callers must work unchanged. Run the full mission #1286-era migration test suite as a regression gate before merging.
- **Q4 tie-break is the determinism contract.** Any deviation (random sort, hash-order, etc.) breaks reproducibility. Reviewer should confirm the sort key is exactly `(package, id)`, ASCII compare.
- **Single-transaction boundary on SQLite:** SQLite supports DDL inside a transaction for additive ops, which is what v2 emits in this WP. The future destructive/AlterColumn paths will need different transaction handling — out of scope here.
- **Don't write the ledger schema migration in this WP.** That's WP09. Here we just use the existing `MigrationRepository` to write rows.

## Activity Log

- 2026-05-03T00:19:48Z – unknown – Moved to in_progress
- 2026-05-03T00:31:24Z – unknown – Moved to for_review
