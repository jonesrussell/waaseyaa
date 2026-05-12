---
work_package_id: WP03
title: sql-blob backend refactor + behavior-identity tests
dependencies:
- WP01
- WP02
requirement_refs:
- FR-007
- FR-008
- FR-009
- FR-010
- FR-049
- FR-052
planning_base_branch: kitty/mission-entity-storage-v2-01KRCDDC
merge_target_branch: kitty/mission-entity-storage-v2-01KRCDDC
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-entity-storage-v2-01KRCDDC. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-entity-storage-v2-01KRCDDC unless the human explicitly redirects the landing branch.
subtasks:
- T013
- T014
- T015
- T016
- T017
agent: "claude:opus:reviewer:reviewer"
shell_pid: "414776"
history:
- timestamp: '2026-05-11T23:30:00+00:00'
  actor: claude
  action: wp_prompt_generated
  note: Generated from wps.yaml during tasks_packages step.
authoritative_surface: packages/entity-storage/src/Backend
execution_mode: code_change
owned_files:
- packages/entity-storage/src/Backend/SqlBlobBackend.php
- packages/entity-storage/src/SqlEntityStorage.php
- packages/entity-storage/src/SqlSchemaHandler.php
- packages/entity-storage/tests/Integration/BehaviorIdentity/**
tags: []
---

# WP03: sql-blob backend refactor + behavior-identity tests

## Objective

Deliver the **sql-blob backend refactor + behavior-identity tests** scope of mission `entity-storage-v2-01KRCDDC` (M-001).

Requirement coverage: `FR-007`, `FR-008`, `FR-009`, `FR-010`, `FR-049`, `FR-052`.

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

### T013: Extract _data path into SqlBlobBackend

Create `packages/entity-storage/src/Backend/SqlBlobBackend.php` implementing `FieldStorageBackendInterface`.

- `id() = 'sql-blob'`.
- Lift the JSON-blob persistence behavior from `SqlEntityStorage::splitForStorage()` / `mapRowToEntity()` into per-field `read()` / `write()`.
- Preserve `_data` TEXT column shape exactly — same JSON keys, same NULL handling.
- `supportsQuery` returns true ONLY when the query targets entity-key columns (id/uuid/bundle/langcode). Non-key field queries return false → `UnsupportedQueryException` at definition-validation time (WP06 integration).

**Files**: `packages/entity-storage/src/Backend/SqlBlobBackend.php` (new, ~220 lines).

### T014: Update SqlSchemaHandler for sql-blob path only

Modify `packages/entity-storage/src/SqlSchemaHandler.php`.

- Add `$primaryBackendId` parameter to schema-build entry points.
- When primary is `sql-blob`: emit `_data` TEXT column (current behavior).
- When primary is `sql-column`: defer to `SqlColumnSchemaBuilder` (WP05).

**Files**: `packages/entity-storage/src/SqlSchemaHandler.php` (modify, ~60 line delta).

### T015: Snapshot legacy SqlEntityStorage behavior

Author behavior-identity snapshot tests in `packages/entity-storage/tests/Integration/BehaviorIdentity/Baseline/`.

- For each fixture entity type covering: string, int, bool, datetime, json field types.
- Snapshot: schema output, CRUD round-trip (create/read/update/delete), query results (find/findBy/count), `_data` column contents (verbatim JSON).
- Snapshots are captured BEFORE refactor begins. They are the gate for T016.

**Files**: `packages/entity-storage/tests/Integration/BehaviorIdentity/Baseline*.php` (new, ~300 lines).

### T016: Verify byte-identical post-refactor behavior

After T013/T014 land, re-run the T015 snapshots through the refactored stack. Every output must be byte-identical to the captured baseline. Any deviation fails the test — no fuzz match, no normalization.

This is FR-008 — the hardest gate in the mission.

**Files**: `packages/entity-storage/tests/Integration/BehaviorIdentity/PostRefactorTest.php` (new, ~200 lines).

### T017: sql-blob conformance test (FR-049 partial)

Run `FieldStorageBackendContractTestCase` (built in WP12 — placeholder until then) against `SqlBlobBackend`. Until WP12 ships the harness, write a temporary `SqlBlobMinimumSurfaceTest.php` exercising: read/write/delete round-trip, idempotent re-write, `supportsQuery` contract on entity-key vs non-key fields.

**Files**: `packages/entity-storage/tests/Integration/BehaviorIdentity/SqlBlobMinimumSurfaceTest.php` (new, ~150 lines).

## Test Strategy

- Use PHPUnit 10.5 (project-mandated; **do not** pass `-v` — PHPUnit 10.5 rejects it).
- Unit tests under `packages/<pkg>/tests/Unit/`.
- Integration tests under `packages/<pkg>/tests/Integration/`.
- Contract tests under `packages/<pkg>/tests/Contract/` use `#[CoversNothing]`.
- In-memory storage for tests: `DBALDatabase::createSqlite()` (project gotcha — DBAL fetch mode is `fetchAssociative()`).
- Mock final classes with real instances + temp dirs (PHPUnit `createMock()` fails on `final class`).
- Log assertions: capture `Waaseyaa\Foundation\Log\LoggerInterface` via a recording fake; do not use `psr/log`.

## Definition of Done

- All subtasks (5) complete: T013, T014, T015, T016, T017.
- All requirement refs covered by tests: FR-007, FR-008, FR-009, FR-010, FR-049, FR-052.
- `composer cs-check` clean (run twice with cache cleared if needed per project gotcha).
- `composer phpstan` clean.
- `bin/check-package-layers` clean (no upward edges introduced).
- `bin/audit-dead-code` reports no new findings (mark intentional scaffolding with `@api`).
- `bin/check-composer-policy` clean (no `@dev`, no wildcard internal constraints, `self.version` only in root, internal `waaseyaa/*` constraints equal `^<current-tag>`).

## Risks

- **Behavior drift in sql-blob refactor** (research risk #1). Mitigation: snapshot baseline BEFORE refactor; byte-identical comparison gate.
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
spec-kitty agent action implement WP03 --agent sonnet
```

## Review Command

```bash
spec-kitty agent action review WP03 --agent opus
```

Per mission agent assignments (mission.json): implementer = `sonnet`, reviewer = `opus`. Escalation target = `opus-as-implementer` after N=2 rejections.

## Activity Log

- 2026-05-12T01:29:45Z – claude:sonnet:implementer:implementer – shell_pid=412867 – Started implementation via action command
- 2026-05-12T01:37:26Z – claude:sonnet:implementer:implementer – shell_pid=412867 – Ready for review: sql-blob backend refactor + byte-identity gate (T013-T017)
- 2026-05-12T01:37:46Z – claude:opus:reviewer:reviewer – shell_pid=414776 – Started review via action command
- 2026-05-12T01:40:25Z – claude:opus:reviewer:reviewer – shell_pid=414776 – Cycle 1 approved: FR-008 byte-identity gate sharp (raw assertSame on _data), all 19 BehaviorIdentity tests + cs-check + phpstan + layers + policy clean; legacy splitForStorage retained intentionally for WP06 swap
