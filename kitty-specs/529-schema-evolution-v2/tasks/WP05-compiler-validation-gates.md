---
work_package_id: WP05
title: Compiler validation gates + capability matrix
dependencies:
- WP04
requirement_refs:
- C-005
- C-006
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T024
- T025
- T026
- T027
- T028
- T029
- T030
history:
- date: '2026-05-02'
  note: Initial generation by /spec-kitty.tasks-packages.
authoritative_surface: packages/foundation/src/Schema/Compiler/Validation
execution_mode: code_change
mission_id: 01KQN41MQD3Y6PG0PES8XX166F
mission_slug: 529-schema-evolution-v2
owned_files:
- packages/foundation/src/Schema/Compiler/Validation/**
- packages/foundation/tests/Unit/Schema/Compiler/Validation/**
tags:
- foundation
- compiler
- validation
- safety-gates
---

# WP05 — Compiler validation gates + capability matrix

## Objective

Layer **safety gates** on top of WP04's additive compiler. After this WP:

- The compiler refuses unknown op kinds with structured errors (operator-readable codes).
- `AlterColumn` is rejected on SQLite per Q5 with stable code `ALTER_COLUMN_UNSUPPORTED_SQLITE_V1`.
- `AddForeignKey` and `DropForeignKey` are rejected on SQLite per Q6 with stable code `FOREIGN_KEY_UNSUPPORTED_SQLITE_V1`.
- `DropColumn` and `DropIndex` are blocked unless the plan carries an explicit `dangerAccepted: true` flag (added at the policy layer here).
- Illegal op ordering (e.g. `AddIndex` referencing a column not yet added in the same composite) is detected and fails fast with a structured code.
- A SQLite capability matrix exists as a documented reference (ADR-style) and as a runtime data structure consulted by the compiler.
- All gates produce errors that meet operator-diagnostics conventions: stable code, human-readable detail, no raw filesystem paths in production output.

## Context

Read before starting:

- `docs/specs/schema-evolution-v2.md` §5.3 (validation rules), §11 (safety gates), §15 Q5 / Q6.
- WP04 output: `Schema/Compiler/Sqlite/SqliteCompiler.php`, `SqliteCapabilities`.
- Existing operator diagnostics conventions: `docs/specs/operator-diagnostics.md` and `packages/foundation/src/Diagnostic/`.

## Subtasks

### T024 — Reject unknown operation kinds

**Purpose:** A `match` on `OpKind` should be exhaustive; if a new op type is added without compiler coverage, fail loud.

**Steps:**
1. In `SqliteCompiler::compile()`, the `match` over `op->kind()` declares every supported `OpKind`.
2. PHP 8.4 `match` already throws `\UnhandledMatchError`; wrap with a structured exception that carries `code = UNKNOWN_OP_KIND` and the offending kind name in the message.

**Files:** `Schema/Compiler/Validation/UnknownOpKindException.php`, modify `SqliteCompiler.php`.

### T025 — Reject `AlterColumn` on SQLite (Q5)

**Purpose:** Stable diagnostic surface so callers know this is a v1 limitation, not a bug.

**Steps:**
1. Add `AlterColumnTranslator` that always throws `AlterColumnUnsupportedException(code: 'ALTER_COLUMN_UNSUPPORTED_SQLITE_V1')`.
2. The exception message names the table + column + a pointer: "AlterColumn is not supported on SQLite in v1; split as drop+add (with explicit danger flag) or wait for the SQLite table-rebuild ADR."

**Files:** `Schema/Compiler/Validation/AlterColumnUnsupportedException.php`, `Schema/Compiler/Sqlite/Translator/AlterColumnTranslator.php`.

### T026 — Block `DropColumn` / `DropIndex` without policy flag

**Purpose:** Destructive ops require explicit consent.

**Steps:**
1. Add a `PlanPolicy` value type at `Schema/Compiler/Validation/PlanPolicy.php`: `final readonly class` with `bool $allowDestructive` (default false), passed alongside `CompositeDiff` into `SqliteCompiler::compile()`.
2. Update `SqliteCompiler::compile(CompositeDiff $diff, PlanPolicy $policy = new PlanPolicy()): CompiledMigrationPlan`.
3. `DropColumnTranslator` / `DropIndexTranslator` consult `$policy->allowDestructive`; when false, throw `DestructiveOpBlockedException(code: 'DESTRUCTIVE_OP_BLOCKED', op: 'drop_column' | 'drop_index')`.

**Files:** `PlanPolicy.php`, `DestructiveOpBlockedException.php`, `Translator/DropColumnTranslator.php`, `Translator/DropIndexTranslator.php`.

### T027 — Reject FK ops on SQLite (Q6)

**Purpose:** `FOREIGN_KEY_UNSUPPORTED_SQLITE_V1` per ratified resolution.

**Steps:**
1. Add `ForeignKeyTranslator` that throws `ForeignKeyUnsupportedException(code: 'FOREIGN_KEY_UNSUPPORTED_SQLITE_V1')` for both `AddForeignKey` and `DropForeignKey`.
2. Message names the table + the constraint and points at the future MySQL/Postgres path.

**Files:** `Schema/Compiler/Validation/ForeignKeyUnsupportedException.php`, `Schema/Compiler/Sqlite/Translator/ForeignKeyTranslator.php`.

### T028 — Detect illegal ordering

**Purpose:** Catch ops out of order at compile time, not runtime.

**Steps:**
1. Add `OrderingValidator` that walks the `CompositeDiff` ops once and tracks (table → set of known-extant columns + indexes) using only the ops in this composite (no DB introspection).
2. Rules:
   - `AddIndex(table, columns)`: every column in `columns` must have been added by an earlier `AddColumn` in this composite OR not be referenced as new (we cannot know about pre-existing columns from the diff alone — so the validator checks only same-composite forward references).
   - `RenameColumn(table, from, to)`: the validator marks `from` as no longer the canonical name; subsequent ops must use `to`. Detects `AddColumn(to)` after `RenameColumn(from→to)` as a no-op vs collision (compile-time error).
3. Run `OrderingValidator` before any translator dispatch.
4. On violation, throw `IllegalOpOrderException(code: 'ILLEGAL_OP_ORDER')` with message naming the offending op and the conflicting prior op.

**Files:** `Schema/Compiler/Validation/OrderingValidator.php`, `Schema/Compiler/Validation/IllegalOpOrderException.php`.

### T029 — SQLite capability matrix

**Purpose:** Document and codify what each SQLite version can do, so future contributors can see the matrix without reading translator source.

**Steps:**
1. Create `Schema/Compiler/Sqlite/SqliteCapabilityMatrix.php` as `final readonly class` with static factory methods `for(string $version): SqliteCapabilities`.
2. Document the matrix in `docs/specs/schema-evolution-v2.md` § (new subsection 5.5 or appendix). For each SQLite minor: which ops are supported, which require flags.
3. Coverage: 3.0 / 3.21 / 3.25 (rename column lands) / 3.40 / 3.50 (current).

**Files:** `Schema/Compiler/Sqlite/SqliteCapabilityMatrix.php`. **Spec edit OUT OF SCOPE for this WP** unless the user reopens the spec — instead, write a `docs/specs/sqlite-capability-matrix.md` companion under entity-storage / foundation specs (ADR position) and link from §5.

### T030 — Unit tests for validation rules

**Purpose:** Each gate gets a failing-input test that asserts the structured code.

**Cases:**
1. Compile a `CompositeDiff` with an `AlterColumn` → `AlterColumnUnsupportedException`, code `ALTER_COLUMN_UNSUPPORTED_SQLITE_V1`.
2. Compile with `DropColumn`, default policy → `DESTRUCTIVE_OP_BLOCKED`.
3. Compile with `DropColumn`, `PlanPolicy(allowDestructive: true)` → succeeds, emits an `ExecuteStatement` for the destructive SQL (or — since SQLite cannot drop columns pre-3.35 — defers to the appropriate future translator; document the version cutoff).
4. Compile with `AddForeignKey` → `FOREIGN_KEY_UNSUPPORTED_SQLITE_V1`.
5. Compile a `CompositeDiff` containing `AddIndex(table=t, columns=['c1','c2'])` without prior `AddColumn(c1)` in same composite → `ILLEGAL_OP_ORDER` if `c1` is not in known schema. (For first-pass v1, we only check same-composite forward references; document this.)
6. Compile with a fabricated `OpKind` injected through reflection → `UNKNOWN_OP_KIND`.

**Files:** `tests/Unit/Schema/Compiler/Validation/*Test.php`.

## Definition of Done

- [ ] All five rejection paths (Alter, Drop without flag, FK, illegal ordering, unknown kind) throw structured exceptions with stable codes.
- [ ] `PlanPolicy` value type exists; `SqliteCompiler::compile()` accepts it.
- [ ] Capability matrix documented in a spec/ADR file and codified in `SqliteCapabilityMatrix`.
- [ ] `bin/check-package-layers`, `bin/check-composer-policy`, PHPStan level 5 clean.
- [ ] Tests cover every gate.

## Risks / Reviewer guidance

- **Stable codes are the contract:** these strings (`ALTER_COLUMN_UNSUPPORTED_SQLITE_V1` etc.) end up in operator dashboards and CI logs. Treat them as a public API. Any change is a breaking change.
- **`DropColumn` on old SQLite:** Don't try to make the destructive-allowed path silently emit a table-rebuild. v1's contract is "destructive is blocked OR explicitly accepted with a warning that you're on your own for SQLite-version compatibility." A rebuild story is a future ADR.
- **Don't introspect the DB here.** OrderingValidator walks only the composite's ops. Database introspection is a verify-mode concern (WP10), not a compile-time concern.
- **Capability matrix lives close to the compiler**, not in `docs/specs/schema-evolution-v2.md`. The spec ratification window is closed; this is implementation-adjacent reference material.

## Activity Log

- 2026-05-03T00:01:40Z – unknown – Moved to in_progress
