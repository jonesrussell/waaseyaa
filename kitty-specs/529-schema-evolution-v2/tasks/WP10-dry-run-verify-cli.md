---
work_package_id: WP10
title: dry-run + verify CLI surface
dependencies:
- WP04
- WP06
- WP09
requirement_refs:
- FR-005
- C-008
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T056
- T057
- T058
- T059
- T060
- T061
history:
- date: '2026-05-02'
  note: Initial generation by /spec-kitty.tasks-packages.
authoritative_surface: packages/cli/src/Command/Migrate
execution_mode: code_change
mission_id: 01KQN41MQD3Y6PG0PES8XX166F
mission_slug: 529-schema-evolution-v2
owned_files:
- packages/cli/src/Command/MigrateCommand.php
- packages/cli/src/Command/Migrate/**
- packages/cli/tests/Unit/Command/Migrate/**
tags:
- cli
- dry-run
- verify
- operator-diagnostics
---

# WP10 — dry-run + verify CLI surface

## Objective

Wire the operator-facing surface for the v2 execution model per Q8. After this WP:

- `bin/waaseyaa migrate --dry-run` compiles all pending plans (legacy + v2 in unified DAG order) and prints what WOULD execute. Zero ledger writes, zero SQL on the live DB.
- `bin/waaseyaa migrate --verify` walks the ledger + live schema and reports drift. Zero apply, zero ledger writes.
- Output has both human-readable mode (default) and `--json` for CI / dashboards.
- Production output sanitization: no raw filesystem paths in error strings; structured codes only.
- Operator diagnostic codes integrate with the existing `Waaseyaa\Foundation\Diagnostic` conventions.
- One CLI surface, two flags — no new command family per Q8.

## Context

Read before starting:

- `docs/specs/schema-evolution-v2.md` §7 (execution model), §15 Q8.
- `docs/specs/operator-diagnostics.md` — code conventions.
- WP04: `SqliteCompiler::compile()`.
- WP06: `Migrator` topological order.
- WP09: `MigrationRepository::verifyChecksum()`, `allWithChecksums()`.
- Existing `packages/cli/src/Command/MigrateCommand.php` — the entry point we extend.

## Subtasks

### T056 — `--dry-run` flag on `bin/waaseyaa migrate`

**Purpose:** Compile and print without applying.

**Steps:**
1. Add `--dry-run` boolean flag to `MigrateCommand`.
2. When set, the command builds the same `MigrationGraph` as a real apply, but instead of executing each node:
   - **v2 nodes:** call `SqliteCompiler::compile($plan->root)` and accumulate `CompiledStep` lists.
   - **Legacy nodes:** record the migration name and a "<legacy migration — body not introspected>" placeholder; we cannot pre-execute imperative migrations.
3. Print to stdout. Default human format: one section per node showing migration_id, kind, dependencies, and (for v2) the SQL preview. JSON format with `--json`.

**Files:** modify `MigrateCommand.php`, add `Migrate/DryRunFormatter.php`.

### T057 — `--verify` flag on `bin/waaseyaa migrate`

**Purpose:** Compare expected vs actual schema state.

**Steps:**
1. Add `--verify` boolean flag (mutually exclusive with `--dry-run`; if both, error with `INCOMPATIBLE_FLAGS`).
2. Verify pass:
   - Iterate `MigrationRepository::allWithChecksums()`.
   - For each row with a non-null `checksum`: locate the corresponding `MigrationInterfaceV2` (or legacy `Migration`) in the loaded set; recompute its checksum; compare.
   - Mismatch → emit a structured diagnostic.
   - Stored null + no source available (e.g. migration files removed) → emit `LEDGER_ORPHAN` diagnostic.
   - Live DB introspection for "did all expected ops actually apply" is OUT OF SCOPE here — that's a future round-trip enhancement (§12.5 non-goal in v1). Verify mode v1 is checksum-vs-source comparison only.
3. Exit code: 0 if all match, non-zero if any mismatch / orphan.

**Files:** modify `MigrateCommand.php`, add `Migrate/VerifyRunner.php`, `Migrate/VerifyFormatter.php`.

### T058 — Structured JSON output

**Purpose:** Operators / CI consume machine-readable output.

**Steps:**
1. `--json` flag on `migrate` (works with `--dry-run` and `--verify`).
2. Schema (locked here, document in CHANGELOG):
   - dry-run: `{ "kind": "dry_run", "nodes": [{ "id", "kind", "dependencies", "steps": [...] }], "summary": { "v2_count", "legacy_count", "would_apply" } }`.
   - verify: `{ "kind": "verify", "results": [{ "migration", "status": "match|mismatch|unknown|orphan", "stored_checksum", "computed_checksum?" }], "summary": { "match", "mismatch", "unknown", "orphan" } }`.

**Files:** modify the dry-run + verify formatters.

### T059 — Production output sanitization

**Purpose:** Per §7.3, no raw filesystem paths in production output.

**Steps:**
1. In production mode (`!$kernel->isDevelopmentMode()`), formatter strips absolute paths from error messages. Replace with package-qualified migration IDs.
2. Operator-pointing tips (e.g. "see migration `waaseyaa/groups:v2:add-archived-flag`") replace any "in /home/.../packages/...".
3. Add a `OutputSanitizer` helper used by both formatters.

**Files:** `Migrate/OutputSanitizer.php`, modify formatters.

### T060 — Operator diagnostic codes integration

**Purpose:** Reuse existing diagnostic-code conventions.

**Steps:**
1. Map exceptions from WP04/d/e to diagnostic codes already in `packages/foundation/src/Diagnostic/DiagnosticCode.php` where matches exist; add new entries for v2-specific codes (`CHECKSUM_MISMATCH`, `LEDGER_ORPHAN`, `MIGRATION_CYCLE`, `UNKNOWN_DEPENDENCY`).
2. JSON output's diagnostic entries match the structure used by `HealthChecker` for consistency.

**Files:** modify `DiagnosticCode.php`, formatters.

### T061 — CLI tests + integration test

**Cases:**
1. `migrate --dry-run`: pending plan list with mixed kinds → output includes both, no ledger row written, no SQL applied to a `:memory:` SQLite.
2. `migrate --dry-run --json`: output is valid JSON matching the documented schema.
3. `migrate --verify`: ledger has 3 rows, all checksums match → exit 0, summary `{match: 3, mismatch: 0, ...}`.
4. `migrate --verify`: one ledger row has mismatched checksum → exit non-zero, formatter names the migration.
5. `migrate --dry-run --verify`: → `INCOMPATIBLE_FLAGS` error, exit code 2.
6. Production sanitization: a fixture migration whose canonical-JSON contains an absolute path → JSON output strips it.

**Files:** `packages/cli/tests/Unit/Command/Migrate/DryRunCommandTest.php`, `VerifyCommandTest.php`, `tests/Integration/Cli/MigrateDryRunVerifyTest.php`.

## Definition of Done

- [ ] `bin/waaseyaa migrate --dry-run` and `--verify` both work; mutually exclusive.
- [ ] `--json` output matches documented schema; covered by tests.
- [ ] Production output strips raw paths.
- [ ] Diagnostic codes integrated with existing convention.
- [ ] PHPStan level 5 clean. `bin/check-package-layers`, `bin/check-composer-policy` clean.
- [ ] Tests cover all 6 cases.

## Risks / Reviewer guidance

- **No new top-level commands.** Q8 ratified: extend `migrate` with flags. If the diff adds `migrate:plan` or `schema:verify`, reject and consolidate.
- **Verify is checksum-only in v1.** Live DB introspection for "did the schema actually apply" is the §12.5 round-trip non-goal. Don't sneak it in.
- **Don't make `--verify` write to the ledger.** Even tempting "update last-verified-at" timestamps re-open the question of whether verify is read-only. It is. Lock it.
- **Mutually-exclusive flags:** `--dry-run --verify` together is operator confusion. Fail fast with `INCOMPATIBLE_FLAGS`.
- **Production sanitization is a security/UX concern, not a stylistic one.** Operator dashboards leak paths into Slack / Sentry. Test it explicitly.
