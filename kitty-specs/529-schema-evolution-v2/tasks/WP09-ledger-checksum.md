---
work_package_id: WP09
title: Ledger checksum + diff_hash extensions
dependencies:
- WP03
- WP06
requirement_refs:
- FR-004
- C-001
- C-002
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T050
- T051
- T052
- T053
- T054
- T055
history:
- date: '2026-05-02'
  note: Initial generation by /spec-kitty.tasks-packages.
authoritative_surface: packages/foundation/src/Migration
execution_mode: code_change
mission_id: 01KQN41MQD3Y6PG0PES8XX166F
mission_slug: 529-schema-evolution-v2
owned_files:
- packages/foundation/src/Migration/MigrationRepository.php
- packages/foundation/src/Migration/LedgerSchema/**
- packages/foundation/tests/Unit/Migration/Ledger/**
tags:
- foundation
- ledger
- checksum
- sha-256
---

# WP09 — Ledger checksum + diff_hash extensions

## Objective

Extend the `waaseyaa_migrations` ledger so v2 applies are auditable per Q1 / Q2:

- Two new nullable columns: `checksum` (SHA-256 over canonical SchemaDiff JSON) and `diff_hash` (SHA-256 over canonical compiled-plan JSON).
- `migration` remains the sole canonical ledger key per Q1 — no `migration_id` column.
- On apply: write `checksum` after successful transaction commit. `diff_hash` is written when a compiled plan is available (always for v2; null for legacy).
- On replay: production refuses to silently re-apply the same `migration` with a different `checksum`. Throws `CHECKSUM_MISMATCH` with the offending row.
- Backfill ADR + script: existing rows lack hashes; the script either leaves them null (verify mode tolerates) or computes them by re-canonicalizing the migration's source.
- Verify mode comparison hooks exist (full verify implementation lands in WP10).

## Context

Read before starting:

- `docs/specs/schema-evolution-v2.md` §6 (ledger), §15 Q1 / Q2.
- WP03 output: `MigrationPlan::checksum()`.
- WP04 output: `CompiledMigrationPlan::diffHash()`.
- WP06 output: Migrator dispatch path that calls these per node.
- Existing `packages/foundation/src/Migration/MigrationRepository.php` and `Migrator.php`.

## Subtasks

### T050 — Schema migration to add `checksum` + `diff_hash` columns

**Purpose:** Schema change for the ledger table itself. Self-application is the natural test.

**Steps:**
1. Author a v2 ledger schema migration: `packages/foundation/src/Migration/LedgerSchema/V2_0001_add_checksum_columns.php` implementing `MigrationInterfaceV2`.
2. The migration's `CompositeDiff` is two `AddColumn` ops: `checksum VARCHAR(64) NULL`, `diff_hash VARCHAR(64) NULL` on table `waaseyaa_migrations`.
3. Run via the Migrator on first boot post-WP09 (the bootstrapper handles "ensure ledger schema is current" — document this path).
4. SQLite: `VARCHAR(64)` is `TEXT` affinity; mention this in the column-spec mapping.

**Files:** `Migration/LedgerSchema/V2_0001_add_checksum_columns.php`.

### T051 — SHA-256 checksum write on apply

**Purpose:** Persist the canonical-JSON hash on every successful apply.

**Steps:**
1. Modify `MigrationRepository::recordApply()` (or whichever method writes ledger rows) to accept `checksum: ?string` and `diff_hash: ?string`.
2. In `Migrator::run()` per-node:
   - Legacy migrations: `checksum = null`, `diff_hash = null` (no canonical form available).
   - v2 migrations: `checksum = $plan->checksum()`, `diff_hash = $compiledPlan->diffHash()`.
3. Write happens AFTER the transaction commits — never write a checksum for a failed apply.

**Files:** modify `MigrationRepository.php`, `Migrator.php`.

### T052 — Replay rejection on checksum mismatch (production)

**Purpose:** Silent re-apply of mismatched checksums is a data-leak / corruption risk; refuse it loud.

**Steps:**
1. Before applying any v2 migration, look up the existing ledger row (if any) by `migration` key.
2. If the row exists AND its `checksum` is non-null AND the new `MigrationPlan::checksum()` differs:
   - **Production:** throw `ChecksumMismatchException(code: 'CHECKSUM_MISMATCH', migration: '<id>', stored: '<hash>', computed: '<hash>')`.
   - **Development (`isDevelopmentMode()`):** log a warning, do not re-apply (assume the existing row is correct).
3. Mirror the dev-vs-prod gate from #1257 WP10's tenancy guard — same pattern, same env detection.

**Files:** modify `Migrator.php`, add `ChecksumMismatchException.php`.

### T053 — Backfill ADR + script for legacy rows

**Purpose:** Existing installs have ledger rows with `checksum = NULL`. Decide their fate.

**Steps:**
1. Author `docs/adr/008-ledger-checksum-backfill.md` capturing the choice: null-tolerate (recommended) vs compute-from-source (slow, error-prone for missing files). Recommended: null is a sentinel meaning "unknown — apply was pre-WP09". Verify mode treats null as "trust but log".
2. CLI script under `bin/waaseyaa migrate:backfill-checksums` (one-shot operator command). Optional in v1 — most installs won't run it.
3. Document in CHANGELOG that null `checksum` rows are valid and intentional for pre-WP09 history.

**Files:** `docs/adr/008-ledger-checksum-backfill.md`, optional `packages/cli/src/Command/MigrateBackfillChecksumsCommand.php`.

### T054 — Verify mode hash comparison hook

**Purpose:** Provide the comparison API; the full verify command lives in WP10.

**Steps:**
1. Add `MigrationRepository::verifyChecksum(string $migration, string $expected): VerifyResult` returning a struct with `match | mismatch | unknown` (the latter when the stored checksum is null).
2. Add `MigrationRepository::allWithChecksums(): list<LedgerRow>` for the verify CLI to iterate.

**Files:** modify `MigrationRepository.php`, add `VerifyResult.php`, `LedgerRow.php` DTOs.

### T055 — Unit + integration tests

**Cases:**
1. Apply v2 plan → `checksum` and `diff_hash` are non-null in the ledger row.
2. Apply legacy migration → `checksum` and `diff_hash` are null.
3. Production env + duplicate apply with same checksum → no-op (already in ledger), no exception.
4. Production env + same `migration` ID with different checksum → `CHECKSUM_MISMATCH` thrown.
5. Development env + same scenario → warning logged, no exception, no re-apply.
6. `verifyChecksum()` returns correct status for match/mismatch/unknown.

**Files:** `tests/Unit/Migration/Ledger/ChecksumWriteTest.php`, `ChecksumReplayGuardTest.php`, `VerifyChecksumTest.php`.

## Definition of Done

- [ ] `waaseyaa_migrations` table has `checksum` + `diff_hash` columns post-WP09 boot.
- [ ] Apply path writes both for v2; null for legacy.
- [ ] `CHECKSUM_MISMATCH` thrown in production, logged in development.
- [ ] Backfill ADR exists at `docs/adr/008-ledger-checksum-backfill.md`.
- [ ] PHPStan level 5 clean. `bin/check-package-layers` clean.
- [ ] All 6 test cases pass.

## Risks / Reviewer guidance

- **No new `migration_id` column.** Q1 ratified: `migration` is the canonical key. Adding a parallel column re-opens the question.
- **Don't compute checksum before commit.** A failed transaction with a checksum already-written gives the operator a misleading audit trail. Order: commit → record.
- **Production gate must be loud.** `CHECKSUM_MISMATCH` is the loudest possible failure mode. Don't hide it behind a config flag — it should always throw in production.
- **Backfill is opt-in.** Most installs should NOT run the backfill script; null checksums for pre-WP09 rows are the default and verify mode tolerates them. Document this clearly so operators don't run the script "to be safe."
- **Self-application caveat:** the ledger schema migration applies itself before its own ledger row can be written. The bootstrapper must handle this special case — usually by checking if the columns exist before writing the row, similar to how `ensureTable()` works for entity tables.
