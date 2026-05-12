---
work_package_id: WP01
title: Backend contract + registration
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-005
- FR-006
planning_base_branch: kitty/mission-entity-storage-v2-01KRCDDC
merge_target_branch: kitty/mission-entity-storage-v2-01KRCDDC
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-entity-storage-v2-01KRCDDC. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-entity-storage-v2-01KRCDDC unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-entity-storage-v2-01KRCDDC
base_commit: 573f6889f10d57d0efd1e8b47afbeac4d57d4259
created_at: '2026-05-12T00:16:38.177706+00:00'
subtasks:
- T001
- T002
- T003
- T004
- T005
- T006
- T007
shell_pid: "406389"
agent: "opus"
history:
- timestamp: '2026-05-11T23:30:00+00:00'
  actor: claude
  action: wp_prompt_generated
  note: Generated from wps.yaml during tasks_packages step.
authoritative_surface: packages/entity-storage/src/Backend
execution_mode: code_change
owned_files:
- packages/entity-storage/src/Backend/FieldStorageBackendInterface.php
- packages/entity-storage/src/Backend/HasFieldStorageBackendsInterface.php
- packages/entity-storage/src/Backend/ReservedBackendIds.php
- packages/entity-storage/src/Exception/BackendIdCollisionException.php
- packages/entity-storage/src/Backend/BackendRegistrar.php
- packages/field/src/FieldDefinition.php
- packages/entity-storage/tests/Unit/Backend/**
- packages/field/tests/Unit/FieldDefinitionStoredInTest.php
tags: []
---

# WP01: Backend contract + registration

## Objective

Deliver the **backend contract + registration** scope of mission `entity-storage-v2-01KRCDDC` (M-001).

Requirement coverage: `FR-001`, `FR-002`, `FR-003`, `FR-004`, `FR-005`, `FR-006`.

## Context

- Mission: `entity-storage-v2-01KRCDDC` (M-001 — Multi-Backend Storage with Revisions).
- Canonical spec: `kitty-specs/entity-storage-v2-01KRCDDC/spec.md` (also at `docs/specs/entity-storage-v2.md`).
- Plan: `kitty-specs/entity-storage-v2-01KRCDDC/plan.md`.
- Research + decisions: `kitty-specs/entity-storage-v2-01KRCDDC/research.md`.
- Data model: `kitty-specs/entity-storage-v2-01KRCDDC/data-model.md`.
- Normative contracts: `kitty-specs/entity-storage-v2-01KRCDDC/contracts/`.
- Charter §5.3 governs every stable-surface symbol introduced here.

## Subtasks

### T001: Implement FieldStorageBackendInterface

Create `packages/entity-storage/src/Backend/FieldStorageBackendInterface.php`.

Signature (normative — see `contracts/field-storage-backend.md`):
- `id(): string` — stable backend id.
- `read(EntityInterface $entity, FieldDefinition $field): mixed` — returns null when not stored.
- `write(EntityInterface $entity, FieldDefinition $field, mixed $value): void` — idempotent.
- `delete(EntityInterface $entity): void` — cascades across all this-backend fields.
- `supportsQuery(FieldDefinition $field, EntityQuery $query): bool` — invoked at definition validation time, not query time.

Annotate the interface with `@api` so the dead-code audit (`shipmonk`) marks it used. Add a `declare(strict_types=1);` declaration.

**Files**: `packages/entity-storage/src/Backend/FieldStorageBackendInterface.php` (new, ~40 lines incl. doc-comment).

### T002: Implement HasFieldStorageBackendsInterface

Create `packages/entity-storage/src/Backend/HasFieldStorageBackendsInterface.php`.

Single method: `fieldStorageBackends(): list<FieldStorageBackendInterface>`. Provider capability marker discovered via `extra.waaseyaa.providers` in composer.json (existing convention — see `HasNativeCommandsInterface`).

**Files**: `packages/entity-storage/src/Backend/HasFieldStorageBackendsInterface.php` (new, ~25 lines).

### T003: Implement ReservedBackendIds constants

Create `packages/entity-storage/src/Backend/ReservedBackendIds.php`.

- `final class ReservedBackendIds` with `public const SQL_BLOB = 'sql-blob';`, `SQL_COLUMN = 'sql-column';`, `VECTOR = 'vector';`.
- `public static function all(): list<string>`.
- `@api` annotation.

**Files**: `packages/entity-storage/src/Backend/ReservedBackendIds.php` (new, ~25 lines).

### T004: Implement BackendIdCollisionException

Create `packages/entity-storage/src/Exception/BackendIdCollisionException.php`.

- Extends `\RuntimeException`.
- Constructor accepts `string $id, string $firstFqcn, string $secondFqcn`.
- Public readonly properties; message includes all three.
- `@api` annotation.

**Files**: `packages/entity-storage/src/Exception/BackendIdCollisionException.php` (new, ~30 lines).

### T005: Implement BackendRegistrar boot integration

Create `packages/entity-storage/src/Backend/BackendRegistrar.php`.

Responsibilities:
- Discover providers via `PackageManifestCompiler` capability scan for `HasFieldStorageBackendsInterface`.
- Iterate providers in Composer `installed.json` order; allow optional integer `priority` constant on the provider for tie-breaking.
- Index backends by `id()`.
- Raise `BackendIdCollisionException` on duplicate ids.
- Reject reserved-id misuse: only the framework may register `sql-blob`, `sql-column`, `vector`. Third parties registering reserved ids fail boot via `BackendIdCollisionException`.
- Resolve open question Q1 (research §3): registration order = installed.json + optional priority override.

**Files**: `packages/entity-storage/src/Backend/BackendRegistrar.php` (new, ~120 lines).

### T006: Add FieldDefinition::storedIn() and indexed()

Modify `packages/field/src/FieldDefinition.php`.

Add two fluent methods:
- `storedIn(string $backendId): self` — sets the per-field backend override. Stores in a private `?string` property.
- `indexed(): self` — marks the field for B-tree indexing under `sql-column`. Stores in a private `bool` property.

Both methods must be additive; existing call sites are not modified. At boot, `BackendRegistrar` validates the `storedIn()` id against the registered set and throws on unknown ids.

**Files**: `packages/field/src/FieldDefinition.php` (modify, ~40 line delta).

### T007: Unit tests for backend registration

Create unit tests covering:
- Two providers registering the same non-reserved id → `BackendIdCollisionException`.
- Third-party provider registering `sql-blob` → `BackendIdCollisionException` (reserved-id rule).
- `installed.json` ordering determines registration order absent priority.
- Explicit `priority: int` override wins ties.
- `FieldDefinition::storedIn('unknown-id')` → validation failure at boot.

**Files**:
- `packages/entity-storage/tests/Unit/Backend/BackendRegistrarTest.php` (new, ~150 lines).
- `packages/field/tests/Unit/FieldDefinitionStoredInTest.php` (new, ~80 lines).

## Test Strategy

- Use PHPUnit 10.5 (project-mandated; **do not** pass `-v` — PHPUnit 10.5 rejects it).
- Unit tests under `packages/<pkg>/tests/Unit/`.
- Integration tests under `packages/<pkg>/tests/Integration/`.
- Contract tests under `packages/<pkg>/tests/Contract/` use `#[CoversNothing]`.
- In-memory storage for tests: `DBALDatabase::createSqlite()` (project gotcha — DBAL fetch mode is `fetchAssociative()`).
- Mock final classes with real instances + temp dirs (PHPUnit `createMock()` fails on `final class`).
- Log assertions: capture `Waaseyaa\Foundation\Log\LoggerInterface` via a recording fake; do not use `psr/log`.

## Definition of Done

- All subtasks (7) complete: T001, T002, T003, T004, T005, T006, T007.
- All requirement refs covered by tests: FR-001, FR-002, FR-003, FR-004, FR-005, FR-006.
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
spec-kitty agent action implement WP01 --agent sonnet
```

## Review Command

```bash
spec-kitty agent action review WP01 --agent opus
```

Per mission agent assignments (mission.json): implementer = `sonnet`, reviewer = `opus`. Escalation target = `opus-as-implementer` after N=2 rejections.

## Activity Log

- 2026-05-12T00:16:39Z – sonnet – shell_pid=400063 – Assigned agent via action command
- 2026-05-12T00:22:04Z – sonnet – shell_pid=400063 – Ready for review
- 2026-05-12T00:22:59Z – opus – shell_pid=401893 – Started review via action command
- 2026-05-12T00:35:48Z – opus – shell_pid=401893 – Cycle 2: addressed items 1, 3, 4, 5; item 2 resolved upstream. tmp/ is pre-existing PHPStan artifact, not WP01 owned.
- 2026-05-12T00:50:12Z – opus – shell_pid=406389 – Started review via action command
