---
work_package_id: WP02
title: SchemaDiff atomic types + CompositeDiff (foundation)
dependencies:
- WP01
requirement_refs:
- FR-001
- C-002
- C-003
- C-007
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main.
subtasks:
- T004
- T005
- T006
- T007
- T008
- T009
- T010
history:
- date: '2026-05-02'
  note: Initial generation by /spec-kitty.tasks-packages.
authoritative_surface: packages/foundation/src/Schema/Diff
execution_mode: code_change
mission_id: 01KQN41MQD3Y6PG0PES8XX166F
mission_slug: 529-schema-evolution-v2
owned_files:
- packages/foundation/src/Schema/Diff/**
- packages/foundation/tests/Unit/Schema/Diff/**
tags: [foundation, schema-diff, data-model]
---

# WP02 — SchemaDiff atomic types + CompositeDiff (foundation)

## Objective

Land the **pure data** layer of the SchemaDiff engine in `waaseyaa/foundation` (Layer 0). After this WP:

- Every supported structural change is represented by an immutable, readonly PHP 8.4 type with no SQL strings, no DB I/O, no static state.
- A `CompositeDiff` carries an ordered, immutable list of atomic ops with deterministic equality and SHA-256 hashing per Q2.
- Canonical JSON serialization (sorted keys, UTF-8, stable integer/float treatment) is implemented so two diffs representing the same intent hash to the same `checksum`.
- The empty plan is `CompositeDiff([])` per Q3 — no separate "empty" type.

The foundation surface produced here is what every downstream WP consumes: WP03 (interface payload), WP04 (compiler input), WP07 (entity factory output), WP09 (checksum source).

## Context

Read before starting:

- `docs/specs/schema-evolution-v2.md` §3 (data model), §3.3 (atomic operations table), §3.4 (composite/scoped), §15 Q2 / Q3 / Q7 (binding decisions).
- `kitty-specs/529-schema-evolution-v2/spec.md` Ratified Resolutions table.
- `packages/foundation/src/` for layer conventions; foundation imports nothing from L1+.
- Existing column-spec source of truth: `packages/foundation/src/` and `packages/entity-storage/src/SqlSchemaHandler.php::deriveColumnSpec()` (consume its semantic shape, do not depend on entity-storage from foundation).

## Subtasks

### T004 — `SchemaDiffOp` base + kind enum

**Purpose:** A single readonly contract every atomic op implements, plus an enum-backed `OpKind` that supports exhaustive `match` and serialization.

**Steps:**
1. Create `packages/foundation/src/Schema/Diff/SchemaDiffOp.php` as a sealed-style interface. PHP 8.4 has no `sealed` keyword; enforce via a `final readonly` discipline on implementations + a registered allow-list in the canonicalizer.
2. Create `packages/foundation/src/Schema/Diff/OpKind.php` as a backed enum: `add_column`, `alter_column`, `drop_column`, `add_index`, `drop_index`, `add_foreign_key`, `drop_foreign_key`, `rename_column`, `rename_table`.
3. The interface must declare: `public function kind(): OpKind;` and `public function toCanonical(): array;` (returns the canonical-JSON dict for that op).

**Files:** `Schema/Diff/SchemaDiffOp.php` (new), `Schema/Diff/OpKind.php` (new).

### T005 — Column ops: AddColumn, AlterColumn, DropColumn

**Purpose:** Three readonly types covering additive, in-place, and destructive column changes. Per Q5, `AlterColumn` is a valid value type but the SQLite compiler will reject it — the type must exist for non-SQLite platforms and for v2 future use.

**Steps:**
1. `AddColumn(table, column, ColumnSpec)`. Default policy: allowed.
2. `AlterColumn(table, column, ColumnSpec $newSpec)`. Default policy: gated; SQLite rejects per Q5.
3. `DropColumn(table, column)`. Default policy: blocked unless explicit danger flag at the plan level.
4. Define a `ColumnSpec` value type (`Schema/Diff/ColumnSpec.php`) with `type`, `nullable`, `default`, `length` (nullable), aligned with `SqlSchemaHandler::deriveColumnSpec()` semantic shape — but **no import** from entity-storage.

**Files:** `Schema/Diff/AddColumn.php`, `AlterColumn.php`, `DropColumn.php`, `ColumnSpec.php` (all new).

### T006 — Index ops: AddIndex, DropIndex

**Purpose:** Composite-column indexes with optional explicit names; anonymous index handling deferred to compiler.

**Steps:**
1. `AddIndex(table, columns: list<string>, name: ?string, unique: bool)`.
2. `DropIndex(table, name: ?string, columns: ?list<string>)`. Resolver semantics live in the compiler; the value type just carries identifying fields.

**Files:** `Schema/Diff/AddIndex.php`, `DropIndex.php`.

### T007 — Rename ops: RenameColumn, RenameTable

**Purpose:** Per §3.3, rename is **never** inferred from drop+add. Explicit ops only.

**Steps:**
1. `RenameColumn(table, from: string, to: string)`.
2. `RenameTable(from: string, to: string)`.

**Files:** `Schema/Diff/RenameColumn.php`, `RenameTable.php`.

### T008 — Foreign-key ops: AddForeignKey, DropForeignKey

**Purpose:** The value types exist for the contract; the SQLite compiler will reject per Q6 (`FOREIGN_KEY_UNSUPPORTED_SQLITE_V1`). MySQL/Postgres compilers (future ADR) implement them.

**Steps:**
1. `AddForeignKey(table, ForeignKeySpec)`. `ForeignKeySpec` has: `referencedTable`, `localColumns`, `referencedColumns`, `onDelete`, `onUpdate`, optional `name`.
2. `DropForeignKey(table, name)`.

**Files:** `Schema/Diff/AddForeignKey.php`, `DropForeignKey.php`, `ForeignKeySpec.php`.

### T009 — `CompositeDiff` + canonical JSON + SHA-256 hash

**Purpose:** The composite root that callers and downstream stages consume. Q3 says `CompositeDiff([])` is the empty plan — no separate `Empty` type.

**Steps:**
1. `Schema/Diff/CompositeDiff.php` as `final readonly class` with `public readonly array $ops` (typed `list<SchemaDiffOp>`).
2. `toCanonical(): array` returns `['ops' => [each op's toCanonical()]]` — preserves ops order, no reordering.
3. `toCanonicalJson(): string` produces canonical JSON: object keys sorted lexicographically, UTF-8, integers serialized as integers (no `.0`), arrays preserve order, `null` only where the op declared the field nullable.
4. `checksum(): string` returns `hash('sha256', $this->toCanonicalJson())` — the lifecycle of canonicalization runs once and is the only path to the checksum.
5. `equals(CompositeDiff $other): bool` compares via `checksum()`.
6. Static `CompositeDiff::empty(): self` returns the canonical empty plan.

**Files:** `Schema/Diff/CompositeDiff.php`, `Schema/Diff/CanonicalJson.php` (helper for the sorted-keys + integer-vs-float rules).

### T010 — Unit tests for diff algebra

**Purpose:** Lock the invariants. Test the canonical-JSON rules with golden fixtures so any future change is loud.

**Cases:**
1. Each atomic op: construction, `kind()`, `toCanonical()` shape, immutability (readonly properties).
2. `CompositeDiff` ordering preserves input order.
3. `CompositeDiff::checksum()` is stable across PHP runs (golden hash for a fixed fixture).
4. Two equivalent composites (same ops, same order) hash identically.
5. Reordered ops produce different checksums (intentional — order is part of identity).
6. `CompositeDiff([])->checksum()` is the canonical empty-plan hash; document the value.
7. Canonical JSON: sorted keys, no whitespace, integers vs floats, UTF-8 emoji round-trip.

**Files:** `packages/foundation/tests/Unit/Schema/Diff/*Test.php` (one test class per op, plus `CompositeDiffTest`, `CanonicalJsonTest`, `EmptyPlanTest`).

## Definition of Done

- [ ] All 9 atomic op types exist as `final readonly class` in `packages/foundation/src/Schema/Diff/`.
- [ ] `OpKind` enum, `ColumnSpec`, `ForeignKeySpec`, `CompositeDiff`, `CanonicalJson` exist.
- [ ] `bin/check-package-layers` clean — foundation has no L1+ imports.
- [ ] `bin/check-composer-policy` clean.
- [ ] PHPStan level 5 clean on touched files.
- [ ] Unit tests cover all subtasks with golden hashes for canonical JSON.
- [ ] `composer cs-check` clean.

## Risks / Reviewer guidance

- **Foundation layer purity:** This WP must not import anything from `Waaseyaa\Entity\*`, `Waaseyaa\EntityStorage\*`, or any L1+ symbol. The kernel exemption does not apply here. Reviewer should grep the diff for `use Waaseyaa\Entity` / `use Waaseyaa\EntityStorage` and reject.
- **Canonical JSON edge cases:** Integer-vs-float serialization is a known footgun. Lock specific rules in `CanonicalJson` and document them with examples in the class docblock.
- **`final readonly` on PHP 8.4:** Use constructor-promoted readonly properties. Don't accept overridable constructors — the immutability contract is load-bearing for `checksum()` correctness.
- **`equals()` via checksum:** Tempting to write a property-walker; don't. `equals()` going through `checksum()` keeps a single source of truth for identity.
