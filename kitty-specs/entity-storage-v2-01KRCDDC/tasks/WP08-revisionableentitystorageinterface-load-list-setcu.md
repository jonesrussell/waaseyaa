---
work_package_id: WP08
title: RevisionableEntityStorageInterface + load/list/setCurrent
dependencies:
- WP07
requirement_refs:
- FR-032
- FR-033
- FR-034
- FR-035
- FR-036
- FR-037
planning_base_branch: kitty/mission-entity-storage-v2-01KRCDDC
merge_target_branch: kitty/mission-entity-storage-v2-01KRCDDC
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-entity-storage-v2-01KRCDDC. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-entity-storage-v2-01KRCDDC unless the human explicitly redirects the landing branch.
subtasks:
- T040
- T041
- T042
- T043
- T044
- T045
- T046
agent: "claude:opus:reviewer:reviewer"
shell_pid: "451195"
history:
- timestamp: '2026-05-11T23:30:00+00:00'
  actor: claude
  action: wp_prompt_generated
  note: Generated from wps.yaml during tasks_packages step.
authoritative_surface: packages/entity-storage/src
execution_mode: code_change
owned_files:
- packages/entity-storage/src/RevisionableEntityStorageInterface.php
- packages/entity-storage/src/RevisionableSqlBlobStorage.php
- packages/entity-storage/src/RevisionableSqlColumnStorage.php
- packages/entity-storage/src/RevisionPruner.php
- packages/entity-storage/src/RevisionPruningPolicy.php
- packages/entity-storage/src/RevisionPruningReport.php
- packages/entity-storage/tests/Integration/Revisions/**
tags: []
---

# WP08: RevisionableEntityStorageInterface + load/list/setCurrent

## Objective

Deliver the **revisionableentitystorageinterface + load/list/setcurrent** scope of mission `entity-storage-v2-01KRCDDC` (M-001).

Requirement coverage: `FR-032`, `FR-033`, `FR-034`, `FR-035`, `FR-036`, `FR-037`.

## Context

- Mission: `entity-storage-v2-01KRCDDC` (M-001 — Multi-Backend Storage with Revisions).
- Canonical spec: `kitty-specs/entity-storage-v2-01KRCDDC/spec.md` (also at `docs/specs/entity-storage-v2.md`).
- Plan: `kitty-specs/entity-storage-v2-01KRCDDC/plan.md`.
- Research + decisions: `kitty-specs/entity-storage-v2-01KRCDDC/research.md`.
- Data model: `kitty-specs/entity-storage-v2-01KRCDDC/data-model.md`.
- Normative contracts: `kitty-specs/entity-storage-v2-01KRCDDC/contracts/`.
- Charter §5.3 governs every stable-surface symbol introduced here.

### Dependencies
- `WP07` (must be done before this WP begins).

## Subtasks

### T040: Define RevisionableEntityStorageInterface

Create `packages/entity-storage/src/RevisionableEntityStorageInterface.php` per `contracts/revisionable-entity.md` §2.

- `loadRevision(EntityTypeInterface, int|string $vid): ?RevisionableEntityInterface`.
- `listRevisions(RevisionableEntityInterface $entity): iterable<RevisionableEntityInterface>`.
- `setCurrentRevision(RevisionableEntityInterface $entity, int|string $vid): void`.

**Files**: new, ~45 lines.

### T041: Implement loadRevision

Implement in both backend-specific revision storages:
- `packages/entity-storage/src/RevisionableSqlBlobStorage.php` — query `<table>__revision` by `vid`, hydrate.
- `packages/entity-storage/src/RevisionableSqlColumnStorage.php` — same, joining column data.

Loaded entity has `isCurrentRevision() === ($primary.vid === $loadedVid)`.

**Files**: 2 new files, ~100 lines each.

### T042: Implement listRevisions

Both revision storages return a generator iterating `<table>__revision` in `ORDER BY revision_created_at DESC`. Pagination concerns are caller-side; generator yields lazily.

**Files**: extends T041 files.

### T043: Implement setCurrentRevision

Both revision storages: update primary table `<id> = ?, vid = ?`. Dispatch Before/AfterSave events via the coordinator path. Transactional.

**Files**: extends T041 files.

### T044: SaveContext::withoutNewRevision honored in coordinator

Modify the coordinator save path (owned by WP02; cross-WP edit needs coordination — landing this with WP08 since the revision write logic belongs to revision storage):

When `SaveContext::$withoutNewRevision === true` and the entity type is revisionable: write the primary row in place without inserting a new revision row. The current revision's vid is unchanged.

**Files**: minimal coordinator delta (~20 lines) — coordinate with WP02 owner; spec-kitty lane assignment will sequence.

### T045: Scaffold RevisionPruner

Create `packages/entity-storage/src/RevisionPruner.php` + `RevisionPruningPolicy.php` + `RevisionPruningReport.php`.

- `RevisionPruner` ships with `private bool $enabled = false`. Public `prune()` method returns immediately when disabled (no-op).
- Policy describes the retention rule (keep last N, keep newer than D, keep by author role).
- Report holds before/after counts + skipped reasons.

**Files**: 3 new files, ~80 lines combined.

### T046: Revision integration tests

Cover:
- Save creates new revision (vid increments; primary table vid points to latest).
- `SaveContext::withoutNewRevision()` skips revision insert; vid unchanged.
- `loadRevision($oldVid)` returns the old snapshot with `isCurrentRevision() === false`.
- `listRevisions()` yields in descending order.
- `setCurrentRevision($oldVid)` re-points primary, dispatches AfterSave.

**Files**: `packages/entity-storage/tests/Integration/Revisions/RoundTripTest.php` (new, ~280 lines).

## Test Strategy

- Use PHPUnit 10.5 (project-mandated; **do not** pass `-v` — PHPUnit 10.5 rejects it).
- Unit tests under `packages/<pkg>/tests/Unit/`.
- Integration tests under `packages/<pkg>/tests/Integration/`.
- Contract tests under `packages/<pkg>/tests/Contract/` use `#[CoversNothing]`.
- In-memory storage for tests: `DBALDatabase::createSqlite()` (project gotcha — DBAL fetch mode is `fetchAssociative()`).
- Mock final classes with real instances + temp dirs (PHPUnit `createMock()` fails on `final class`).
- Log assertions: capture `Waaseyaa\Foundation\Log\LoggerInterface` via a recording fake; do not use `psr/log`.

## Definition of Done

- All subtasks (7) complete: T040, T041, T042, T043, T044, T045, T046.
- All requirement refs covered by tests: FR-032, FR-033, FR-034, FR-035, FR-036, FR-037.
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
spec-kitty agent action implement WP08 --agent sonnet
```

## Review Command

```bash
spec-kitty agent action review WP08 --agent opus
```

Per mission agent assignments (mission.json): implementer = `sonnet`, reviewer = `opus`. Escalation target = `opus-as-implementer` after N=2 rejections.

## Activity Log

- 2026-05-12T14:48:00Z – claude:sonnet:implementer:implementer – shell_pid=447935 – Started implementation via action command
- 2026-05-12T14:59:21Z – claude:sonnet:implementer:implementer – shell_pid=447935 – Ready for review: revision storage + load/list/setCurrent (T040-T046). Commit d575dfe55. All gates green: 7676 tests pass, phpstan clean, cs-check clean, layers OK, composer policy OK, dead-code audit OK.
- 2026-05-12T14:59:54Z – claude:opus:reviewer:reviewer – shell_pid=451195 – Started review via action command
- 2026-05-12T15:03:40Z – claude:opus:reviewer:reviewer – shell_pid=451195 – Cycle 1 approved: interface + blob/column impls + coordinator delta + inert pruner; AfterSave-on-failure gate verified; 7676 full suite green
