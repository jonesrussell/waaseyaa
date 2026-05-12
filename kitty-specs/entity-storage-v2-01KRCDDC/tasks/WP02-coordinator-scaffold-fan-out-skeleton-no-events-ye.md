---
work_package_id: WP02
title: Coordinator scaffold + fan-out skeleton (no events yet)
dependencies:
- WP01
requirement_refs:
- FR-017
- FR-018
- FR-019
- FR-020
- FR-021
planning_base_branch: kitty/mission-entity-storage-v2-01KRCDDC
merge_target_branch: kitty/mission-entity-storage-v2-01KRCDDC
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-entity-storage-v2-01KRCDDC. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-entity-storage-v2-01KRCDDC unless the human explicitly redirects the landing branch.
subtasks:
- T008
- T009
- T010
- T011
- T012
agent: "claude:opus:reviewer:reviewer"
shell_pid: "410849"
history:
- timestamp: '2026-05-11T23:30:00+00:00'
  actor: claude
  action: wp_prompt_generated
  note: Generated from wps.yaml during tasks_packages step.
authoritative_surface: packages/entity-storage/src
execution_mode: code_change
owned_files:
- packages/entity-storage/src/EntityStorageCoordinator.php
- packages/entity-storage/src/EntityStorageFactory.php
- packages/entity-storage/src/BackendResolver.php
- packages/entity-storage/tests/Integration/Coordinator/**
tags: []
---

# WP02: Coordinator scaffold + fan-out skeleton (no events yet)

## Objective

Deliver the **coordinator scaffold + fan-out skeleton (no events yet)** scope of mission `entity-storage-v2-01KRCDDC` (M-001).

Requirement coverage: `FR-017`, `FR-018`, `FR-019`, `FR-020`, `FR-021`.

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

## Subtasks

### T008: Implement EntityStorageCoordinator (no events)

Create `packages/entity-storage/src/EntityStorageCoordinator.php`.

Constructor injection: `BackendResolver`, `BackendRegistrar`. NO event dispatcher yet (that arrives in WP04 — leave a `?EventDispatcherInterface $dispatcher = null` constructor slot so WP04 can wire without rewriting the constructor signature).

Methods:
- `read(EntityInterface $entity): EntityInterface` — group fields by backend; for each backend invoke `read()`; assemble.
- `write(EntityInterface $entity): void` — group; write primary backend first; alternates in registration order. No event dispatch yet.
- `delete(EntityInterface $entity): void` — symmetric.

**Files**: `packages/entity-storage/src/EntityStorageCoordinator.php` (new, ~180 lines).

### T009: Implement BackendResolver

Create `packages/entity-storage/src/BackendResolver.php`.

Single responsibility: given an `EntityTypeInterface` and `FieldDefinition`, return the resolved `FieldStorageBackendInterface`. Precedence:
1. `FieldDefinition::storedIn()` override.
2. `EntityType::$primaryStorageBackend` (added in WP07; absence resolves to default).
3. Framework default `sql-blob`.

Resolution failure (unknown id) throws an explicit exception, NOT silent fallback.

**Files**: `packages/entity-storage/src/BackendResolver.php` (new, ~80 lines).

### T010: Wire coordinator into repository + factory

Modify `packages/entity-storage/src/EntityStorageFactory.php` and `packages/entity-storage/src/EntityRepository.php`.

- Bind `EntityStorageCoordinator` in the factory; replace direct `SqlEntityStorage` instantiation behind the existing repository.
- Preserve the canonical pipeline: Entity → Driver → Repository → DBAL. The coordinator sits between repository and per-backend drivers; it does NOT subsume `EntityRepository`.
- See `.claude/rules/entity-storage-invariant.md`.

**Files**:
- `packages/entity-storage/src/EntityStorageFactory.php` (modify, ~60 line delta).
- `packages/entity-storage/src/EntityRepository.php` (modify, ~30 line delta).

### T011: Integration tests for fan-out routing

Create integration tests under `packages/entity-storage/tests/Integration/Coordinator/`:

- Fixture entity type with three fields: one routed to backend A (override), one to backend B (override), one to primary (default).
- Assert correct backend method invoked per field on read/write/delete.
- Use fake backends that record invocations; verify ordering (primary first; alternates in registration order).

**Files**: `packages/entity-storage/tests/Integration/Coordinator/RoutingTest.php` (new, ~200 lines incl. fakes).

### T012: Assertion test for canonical pipeline preservation

Add a regression test that asserts the canonical pipeline survives the coordinator introduction.

Test: load an entity via `EntityRepository::find()`, assert the call path traverses Repository → Coordinator → Backend → DBAL (no direct PDO, no bypass). Use Reflection or trace via spy decorators on each interface.

**Files**: `packages/entity-storage/tests/Integration/Coordinator/PipelineInvariantTest.php` (new, ~80 lines).

## Test Strategy

- Use PHPUnit 10.5 (project-mandated; **do not** pass `-v` — PHPUnit 10.5 rejects it).
- Unit tests under `packages/<pkg>/tests/Unit/`.
- Integration tests under `packages/<pkg>/tests/Integration/`.
- Contract tests under `packages/<pkg>/tests/Contract/` use `#[CoversNothing]`.
- In-memory storage for tests: `DBALDatabase::createSqlite()` (project gotcha — DBAL fetch mode is `fetchAssociative()`).
- Mock final classes with real instances + temp dirs (PHPUnit `createMock()` fails on `final class`).
- Log assertions: capture `Waaseyaa\Foundation\Log\LoggerInterface` via a recording fake; do not use `psr/log`.

## Definition of Done

- All subtasks (5) complete: T008, T009, T010, T011, T012.
- All requirement refs covered by tests: FR-017, FR-018, FR-019, FR-020, FR-021.
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
spec-kitty agent action implement WP02 --agent sonnet
```

## Review Command

```bash
spec-kitty agent action review WP02 --agent opus
```

Per mission agent assignments (mission.json): implementer = `sonnet`, reviewer = `opus`. Escalation target = `opus-as-implementer` after N=2 rejections.

## Activity Log

- 2026-05-12T01:10:13Z – claude:sonnet:implementer:implementer – shell_pid=408152 – Started implementation via action command
- 2026-05-12T01:18:36Z – claude:sonnet:implementer:implementer – shell_pid=408152 – Ready for review: coordinator scaffold + fan-out skeleton (T008-T012). Commit e24b52ea5 on lane-a.
- 2026-05-12T01:18:54Z – claude:opus:reviewer:reviewer – shell_pid=410849 – Started review via action command
- 2026-05-12T01:22:01Z – claude:opus:reviewer:reviewer – shell_pid=410849 – Cycle 1 approved: coordinator scaffold + fan-out skeleton; WP04 dispatcher slot and WP07 primary-backend reflection guard reserved; all gates clean.
