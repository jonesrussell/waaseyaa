---
work_package_id: WP07
title: Revision schema design + EntityType opt-in
dependencies:
- WP05
requirement_refs:
- FR-028
- FR-029
- FR-030
- FR-031
planning_base_branch: kitty/mission-entity-storage-v2-01KRCDDC
merge_target_branch: kitty/mission-entity-storage-v2-01KRCDDC
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-entity-storage-v2-01KRCDDC. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-entity-storage-v2-01KRCDDC unless the human explicitly redirects the landing branch.
subtasks:
- T035
- T036
- T037
- T038
- T039
agent: "claude:opus:reviewer:reviewer"
shell_pid: "445592"
history:
- timestamp: '2026-05-11T23:30:00+00:00'
  actor: claude
  action: wp_prompt_generated
  note: Generated from wps.yaml during tasks_packages step.
authoritative_surface: packages/entity/src
execution_mode: code_change
owned_files:
- packages/entity/src/EntityType.php
- packages/entity/src/RevisionableEntityInterface.php
- packages/entity/src/RevisionableEntityTrait.php
- packages/entity/src/RevisionMetadata.php
- packages/entity-storage/src/Schema/RevisionTableBuilder.php
- packages/entity/tests/Unit/EntityTypeRevisionableTest.php
- packages/entity-storage/tests/Integration/RevisionSchema/**
tags: []
---

# WP07: Revision schema design + EntityType opt-in

## Objective

Deliver the **revision schema design + entitytype opt-in** scope of mission `entity-storage-v2-01KRCDDC` (M-001).

Requirement coverage: `FR-028`, `FR-029`, `FR-030`, `FR-031`.

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

## Subtasks

### T035: Extend EntityType with revision + primary-backend slots

Modify `packages/entity/src/EntityType.php`.

Add two optional constructor params (additive — default-valued so existing call sites still compile):
- `bool $revisionable = false`.
- `?string $primaryStorageBackend = null` (resolved to `sql-blob` if null at boot).

**Files**: `packages/entity/src/EntityType.php` (modify, ~30 line delta).

### T036: Validate revision key consistency

In `EntityType::__construct()` (T035): if `$revisionable === true`, require `entityKeys['revision']` to be a non-empty string. Throw `\InvalidArgumentException` otherwise. Add this check before any other validation so misconfiguration fails fast.

**Files**: extends T035 file.

### T037: Define RevisionableEntityInterface + trait + metadata

Create three files in `packages/entity/src/`:
- `RevisionableEntityInterface.php` (extends `EntityInterface`; adds `revisionId()`, `isCurrentRevision()`, `revisionMetadata()`).
- `RevisionableEntityTrait.php` (default implementations).
- `RevisionMetadata.php` (value object: revisionCreatedAt, revisionAuthor, revisionLog).

**Files**: 3 new files, ~50 lines each.

### T038: Schema generation for revision tables

Create `packages/entity-storage/src/Schema/RevisionTableBuilder.php`.

- For `sql-column` primary: mirror the primary table's column layout (per `contracts/revisionable-entity.md` §3.2) plus revision metadata columns + `vid INTEGER PRIMARY KEY`.
- For `sql-blob` primary: emit `_data TEXT` column + revision metadata + `vid INTEGER PRIMARY KEY` (preserves opt-in path for entity types not yet migrated).

**Files**: `packages/entity-storage/src/Schema/RevisionTableBuilder.php` (new, ~140 lines).

### T039: Generate revision metadata columns

In `RevisionTableBuilder` (T038): emit `revision_created_at TEXT`, `revision_author INTEGER`, `revision_log TEXT` columns on every `<entity>__revision` table. The `revision_author` is a foreign key to the user entity; emit a soft FK only (no on-delete cascade) so revision history survives user deletions.

**Files**: extends T038 file.

## Test Strategy

- Use PHPUnit 10.5 (project-mandated; **do not** pass `-v` — PHPUnit 10.5 rejects it).
- Unit tests under `packages/<pkg>/tests/Unit/`.
- Integration tests under `packages/<pkg>/tests/Integration/`.
- Contract tests under `packages/<pkg>/tests/Contract/` use `#[CoversNothing]`.
- In-memory storage for tests: `DBALDatabase::createSqlite()` (project gotcha — DBAL fetch mode is `fetchAssociative()`).
- Mock final classes with real instances + temp dirs (PHPUnit `createMock()` fails on `final class`).
- Log assertions: capture `Waaseyaa\Foundation\Log\LoggerInterface` via a recording fake; do not use `psr/log`.

## Definition of Done

- All subtasks (5) complete: T035, T036, T037, T038, T039.
- All requirement refs covered by tests: FR-028, FR-029, FR-030, FR-031.
- `composer cs-check` clean (run twice with cache cleared if needed per project gotcha).
- `composer phpstan` clean.
- `bin/check-package-layers` clean (no upward edges introduced).
- `bin/audit-dead-code` reports no new findings (mark intentional scaffolding with `@api`).
- `bin/check-composer-policy` clean (no `@dev`, no wildcard internal constraints, `self.version` only in root, internal `waaseyaa/*` constraints equal `^<current-tag>`).

## Risks

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
spec-kitty agent action implement WP07 --agent sonnet
```

## Review Command

```bash
spec-kitty agent action review WP07 --agent opus
```

Per mission agent assignments (mission.json): implementer = `sonnet`, reviewer = `opus`. Escalation target = `opus-as-implementer` after N=2 rejections.

## Activity Log

- 2026-05-12T14:02:10Z – claude:sonnet:implementer:implementer – shell_pid=441108 – Started implementation via action command
- 2026-05-12T14:16:24Z – claude:sonnet:implementer:implementer – shell_pid=441108 – Ready for review: revision schema + EntityType opt-in (T035-T039)
- 2026-05-12T14:16:52Z – claude:opus:reviewer:reviewer – shell_pid=445592 – Started review via action command
- 2026-05-12T14:19:50Z – claude:opus:reviewer:reviewer – shell_pid=445592 – Cycle 1 approved: revision schema + EntityType opt-in; 7662/7662 green; cleanup of WP02 reflection guard accepted as in-scope follow-through
