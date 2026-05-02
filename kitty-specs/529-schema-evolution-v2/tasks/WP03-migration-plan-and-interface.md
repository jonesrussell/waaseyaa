---
work_package_id: WP03
title: MigrationPlan + MigrationInterfaceV2 (foundation)
dependencies:
- WP02
requirement_refs:
- FR-002
- C-001
- C-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main.
subtasks:
- T011
- T012
- T013
- T014
- T015
- T016
history:
- date: '2026-05-02'
  note: Initial generation by /spec-kitty.tasks-packages.
authoritative_surface: packages/foundation/src/Schema/Migration
execution_mode: code_change
mission_id: 01KQN41MQD3Y6PG0PES8XX166F
mission_slug: 529-schema-evolution-v2
owned_files:
- packages/foundation/src/Schema/Migration/**
- packages/foundation/tests/Unit/Schema/Migration/**
tags: [foundation, migration-interface, contract]
---

# WP03 — `MigrationPlan` + `MigrationInterfaceV2` (foundation)

## Objective

Land the **authoring contract** for v2 migrations. After this WP:

- `MigrationInterfaceV2` is a readonly contract: every v2 migration is a value object holding metadata + a `CompositeDiff` payload. No `up()` / `down()`. No `SchemaBuilder` callbacks. No DB I/O.
- `MigrationPlan` is the immutable DTO that wraps `(metadata, root_composite_diff)` per Q3.
- `migration_id` format is locked: `{package}:v2:{kebab_slug}` per Q1; the existing `migration` ledger column carries this string verbatim — no new identifier column.
- Cross-WP `dependencies` metadata extends today's `$after` semantics so the unified DAG (WP06) has explicit edges.
- The empty plan is `CompositeDiff::empty()` per Q3 — no `Empty` type, no Optional payload.

This WP unblocks WP06 (Migrator integration) and WP09 (ledger checksum source).

## Context

Read before starting:

- `docs/specs/schema-evolution-v2.md` §4 (interface), §6 (ledger), §15 Q1 / Q3.
- WP02 output: `packages/foundation/src/Schema/Diff/` for `CompositeDiff` and op types.
- Existing legacy contract: `packages/foundation/src/Migration/Migration.php` for the imperative interface that v2 coexists with.

## Subtasks

### T011 — `MigrationInterfaceV2` contract

**Purpose:** A single readonly interface every v2 migration implements. No execution methods.

**Steps:**
1. Create `packages/foundation/src/Schema/Migration/MigrationInterfaceV2.php`.
2. Required getters: `migrationId(): string`, `package(): string`, `dependencies(): list<string>`, `plan(): MigrationPlan`.
3. Document explicitly: implementations MUST be `final readonly class`. No abstract base.

**Files:** `MigrationInterfaceV2.php` (new).

### T012 — `MigrationPlan` value type

**Purpose:** The metadata + diff bundle that a `MigrationInterfaceV2` returns.

**Steps:**
1. `Schema/Migration/MigrationPlan.php` as `final readonly class`.
2. Fields: `migrationId: string`, `package: string`, `dependencies: list<string>`, `root: CompositeDiff`.
3. `toCanonical(): array` and `checksum(): string` — both delegate to `root` for the SchemaDiff bytes; metadata is excluded from `checksum()` so identical structural intent across two `migration_id` strings still hash-equates.
4. `diffHash(): ?string` returns null in this WP — the compiled-plan hash is computed by WP04 output, not by the plan itself.

**Files:** `MigrationPlan.php` (new).

### T013 — Empty plan = `CompositeDiff::empty()`

**Purpose:** Lock Q3. No `Empty` type, no `?CompositeDiff`.

**Steps:**
1. Add a `MigrationPlan::isEmpty(): bool` shorthand returning `$this->root->isEmpty()`.
2. Add `CompositeDiff::isEmpty(): bool` to WP02's `CompositeDiff` if not yet present (prefer to add there if WP02 hasn't merged; otherwise this is the seam).
3. Reject any temptation to introduce `MigrationPlan::empty()` — call sites use `new MigrationPlan(...root: CompositeDiff::empty())`.

**Files:** `MigrationPlan.php`, possibly amend `Schema/Diff/CompositeDiff.php` if needed.

### T014 — `migration_id` format + uniqueness

**Purpose:** Lock Q1's stable string pattern.

**Steps:**
1. Create `Schema/Migration/MigrationId.php` as a `final readonly class` value object with constructor validation: must match `/^[a-z0-9-]+\/[a-z0-9-]+:v2:[a-z0-9][a-z0-9-]*$/` (i.e. `{vendor}/{package}:v2:{kebab_slug}`).
2. `MigrationInterfaceV2::migrationId()` may return `string` (for compatibility with the legacy `migration` ledger column type), but factory code SHOULD construct via `MigrationId` and call `toString()`.
3. Document the format in the class docblock with examples: `waaseyaa/groups:v2:add-archived-flag`.

**Files:** `MigrationId.php` (new).

### T015 — Dependency metadata semantics

**Purpose:** Document and lock how cross-migration ordering is expressed.

**Steps:**
1. In `MigrationPlan` and `MigrationInterfaceV2` docblocks, define `dependencies` as: list of `migration_id` strings AND/OR composer package names. Package names are interpreted as "wait until any v2 migration in that package has applied" (matches today's `$after` semantics, scoped to v2 nodes).
2. WP06 is responsible for resolving these strings into DAG edges and detecting unknown references. This WP only locks the data shape.

**Files:** docblock-only changes in `MigrationInterfaceV2.php`, `MigrationPlan.php`.

### T016 — Unit tests for plan structure + metadata validation

**Purpose:** Lock the contract.

**Cases:**
1. `MigrationPlan` construction with a non-empty `CompositeDiff` round-trips through `toCanonical()` / `checksum()`.
2. Empty plan: `MigrationPlan(...root: CompositeDiff::empty())->isEmpty()` is true.
3. `MigrationId` rejects bad formats (no `:v2:`, uppercase, missing vendor) with clear `\InvalidArgumentException`.
4. `migrationId()` round-trips through the value object.
5. Two plans with different metadata but identical roots produce identical `checksum()` — metadata is not part of structural identity (this is intentional; the ledger key is the identity for ordering, not the structural hash).

**Files:** `packages/foundation/tests/Unit/Schema/Migration/*Test.php`.

## Definition of Done

- [ ] `MigrationInterfaceV2`, `MigrationPlan`, `MigrationId` exist in `packages/foundation/src/Schema/Migration/`.
- [ ] Empty-plan path is `CompositeDiff::empty()` (no `Empty` type).
- [ ] `bin/check-package-layers`, `bin/check-composer-policy`, PHPStan level 5 clean.
- [ ] Unit tests cover construction, validation, equality, empty plan.
- [ ] No imports from `Waaseyaa\Entity*` or higher layers.

## Risks / Reviewer guidance

- **Don't add `up()` / `down()`** — the whole point of v2 is no execution methods on the value type. If reviewers see them, reject.
- **`migration_id` format** is locked at this WP. If someone proposes a different shape later, it requires an ADR — not a quiet edit.
- **Metadata vs identity:** `checksum()` covers structural intent only. `migration_id` is the ledger key. Conflating them silently will break Q4's tie-break (`(package ASC, migration ASC)`) when two distinct migrations happen to have identical diffs.
- **Empty plans are real:** A migration that conditionally produces no ops (e.g. "ensure column exists" against a DB that already has it) returns `CompositeDiff::empty()`. The Migrator should treat this as a valid no-op apply and write the ledger row.
