---
work_package_id: WP10
title: Storage-migration generator CLI
dependencies:
- WP05
- WP07
- WP08
requirement_refs:
- FR-041
- FR-042
- FR-043
- FR-044
- FR-045
planning_base_branch: kitty/mission-entity-storage-v2-01KRCDDC
merge_target_branch: kitty/mission-entity-storage-v2-01KRCDDC
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-entity-storage-v2-01KRCDDC. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-entity-storage-v2-01KRCDDC unless the human explicitly redirects the landing branch.
subtasks:
- T051
- T052
- T053
- T054
- T055
- T056
agent: "claude:opus:reviewer:reviewer"
shell_pid: "461353"
history:
- timestamp: '2026-05-11T23:30:00+00:00'
  actor: claude
  action: wp_prompt_generated
  note: Generated from wps.yaml during tasks_packages step.
authoritative_surface: packages/cli/src/Command
execution_mode: code_change
owned_files:
- packages/cli/src/Command/MakeStorageMigrationCommand.php
- packages/cli/src/Command/Migration/StorageMigrationTemplate.php
- packages/cli/src/Command/Migration/StorageMigrationEmitter.php
- packages/cli/src/Command/Migration/BackfillHelper.php
- packages/cli/tests/Integration/MakeStorageMigration/**
tags: []
---

# WP10: Storage-migration generator CLI

## Objective

Deliver the **storage-migration generator cli** scope of mission `entity-storage-v2-01KRCDDC` (M-001).

Requirement coverage: `FR-041`, `FR-042`, `FR-043`, `FR-044`, `FR-045`.

## Context

- Mission: `entity-storage-v2-01KRCDDC` (M-001 — Multi-Backend Storage with Revisions).
- Canonical spec: `kitty-specs/entity-storage-v2-01KRCDDC/spec.md` (also at `docs/specs/entity-storage-v2.md`).
- Plan: `kitty-specs/entity-storage-v2-01KRCDDC/plan.md`.
- Research + decisions: `kitty-specs/entity-storage-v2-01KRCDDC/research.md`.
- Data model: `kitty-specs/entity-storage-v2-01KRCDDC/data-model.md`.
- Normative contracts: `kitty-specs/entity-storage-v2-01KRCDDC/contracts/`.
- Charter §5.3 governs every stable-surface symbol introduced here.

### Dependencies
- `WP05` (must be done before this WP begins).
- `WP07` (must be done before this WP begins).
- `WP08` (must be done before this WP begins).

## Subtasks

### T051: Define MakeStorageMigrationCommand

Create `packages/cli/src/Command/MakeStorageMigrationCommand.php` (Symfony Console).

- Name: `make:storage-migration`.
- Argument: `entity_type_id`.
- Flags: `--target` (default `sql-column`), `--dry-run`, `--force`.
- Exit codes per `contracts/migration-generator-cli.md`.

**Files**: new, ~140 lines.

### T052: Wire field-type → column-type mapping resolver

Inside `StorageMigrationEmitter` (created in this subtask) consume the `TypeMapping` introduced in T029. For each `FieldDefinition`, resolve target column type per platform (SQLite + Postgres). Unmapped types → exit code 4 with stderr message naming the offending field.

**Files**: `packages/cli/src/Command/Migration/StorageMigrationEmitter.php` (new, ~120 lines).

### T053: Emit migration file shape

Generate a PHP migration file under the owning package (or project `migrations/` if no owning package). Class extends `Waaseyaa\Foundation\Migration\Migration`. Methods `up()` / `down()`. Class docblock includes `@expectedReverseSeconds <n>` annotation (T053 default: 30; future PRs may tune).

**Files**: `packages/cli/src/Command/Migration/StorageMigrationTemplate.php` (new, ~100 lines).

### T054: BackfillHelper for _data → columns

Create `packages/cli/src/Command/Migration/BackfillHelper.php`. In the emitted `up()`:

- Read existing rows with `_data` column.
- Decode JSON (with `JSON_THROW_ON_ERROR`).
- Write extracted values into new typed columns.
- Validate row counts pre/post; abort migration with rollback if mismatched.

**Files**: new, ~110 lines.

### T055: Generator integration test

Create a fixture entity type, run `make:storage-migration`, apply via `bin/waaseyaa migrate`, assert schema + backfilled data, then revert via `migrate:rollback` and assert original state. Run on `DBALDatabase::createSqlite()` (in-memory).

**Files**: `packages/cli/tests/Integration/MakeStorageMigration/EndToEndTest.php` (new, ~250 lines).

### T056: Document command in upgrade-guide stub

Stub `docs/upgrades/waaseyaa-alpha-X-to-Y.md` with a "Storage migration cookbook" section, deferring full content to WP11/WP12.

**Files**: minimal stub committed via WP11/WP12; this subtask exists for WP10 acceptance to confirm doc surface exists.

## Test Strategy

- Use PHPUnit 10.5 (project-mandated; **do not** pass `-v` — PHPUnit 10.5 rejects it).
- Unit tests under `packages/<pkg>/tests/Unit/`.
- Integration tests under `packages/<pkg>/tests/Integration/`.
- Contract tests under `packages/<pkg>/tests/Contract/` use `#[CoversNothing]`.
- In-memory storage for tests: `DBALDatabase::createSqlite()` (project gotcha — DBAL fetch mode is `fetchAssociative()`).
- Mock final classes with real instances + temp dirs (PHPUnit `createMock()` fails on `final class`).
- Log assertions: capture `Waaseyaa\Foundation\Log\LoggerInterface` via a recording fake; do not use `psr/log`.

## Definition of Done

- All subtasks (6) complete: T051, T052, T053, T054, T055, T056.
- All requirement refs covered by tests: FR-041, FR-042, FR-043, FR-044, FR-045.
- `composer cs-check` clean (run twice with cache cleared if needed per project gotcha).
- `composer phpstan` clean.
- `bin/check-package-layers` clean (no upward edges introduced).
- `bin/audit-dead-code` reports no new findings (mark intentional scaffolding with `@api`).
- `bin/check-composer-policy` clean (no `@dev`, no wildcard internal constraints, `self.version` only in root, internal `waaseyaa/*` constraints equal `^<current-tag>`).

## Risks

- **Unsafe migrations for large entity types**. Mitigation: `@expectedReverseSeconds` warning + WP11 small-dataset validation first.
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
spec-kitty agent action implement WP10 --agent sonnet
```

## Review Command

```bash
spec-kitty agent action review WP10 --agent opus
```

Per mission agent assignments (mission.json): implementer = `sonnet`, reviewer = `opus`. Escalation target = `opus-as-implementer` after N=2 rejections.

## Activity Log

- 2026-05-12T15:45:15Z – claude:sonnet:implementer:implementer – shell_pid=457540 – Started implementation via action command
- 2026-05-12T16:26:23Z – claude:sonnet:implementer:implementer – shell_pid=457540 – Ready for review: storage-migration generator CLI (T051-T056)
- 2026-05-12T16:26:48Z – claude:opus:reviewer:reviewer – shell_pid=461353 – Started review via action command
- 2026-05-12T16:30:34Z – claude:opus:reviewer:reviewer – shell_pid=461353 – Cycle 1 approved: CLI generator + emitter + backfill helper, all gates green, full suite 7693/7693.
