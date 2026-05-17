---
work_package_id: WP04
title: Lifecycle events in coordinator + PartialSaveException
dependencies:
- WP02
requirement_refs:
- FR-020
- FR-022
- FR-023
- FR-024
- FR-025
- FR-026
- FR-027
- FR-046
- FR-047
- FR-048
planning_base_branch: kitty/mission-entity-storage-v2-01KRCDDC
merge_target_branch: kitty/mission-entity-storage-v2-01KRCDDC
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-entity-storage-v2-01KRCDDC. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-entity-storage-v2-01KRCDDC unless the human explicitly redirects the landing branch.
subtasks:
- T018
- T019
- T020
- T021
- T022
- T023
- T024
agent: "claude:opus:reviewer:reviewer"
shell_pid: "421645"
history:
- timestamp: '2026-05-11T23:30:00+00:00'
  actor: claude
  action: wp_prompt_generated
  note: Generated from wps.yaml during tasks_packages step.
authoritative_surface: packages/entity-storage/src/Event
execution_mode: code_change
owned_files:
- packages/entity-storage/src/Event/**
- packages/entity-storage/src/Exception/PartialSaveException.php
- packages/entity-storage/src/SaveContext.php
- packages/entity-storage/src/CoordinatorLifecycleDispatcher.php
- packages/entity-storage/tests/Integration/Events/**
tags: []
---

# WP04: Lifecycle events in coordinator + PartialSaveException

## Objective

Deliver the **lifecycle events in coordinator + partialsaveexception** scope of mission `entity-storage-v2-01KRCDDC` (M-001).

Requirement coverage: `FR-020`, `FR-022`, `FR-023`, `FR-024`, `FR-025`, `FR-026`, `FR-027`, `FR-046`, `FR-047`, `FR-048`.

## Context

- Mission: `entity-storage-v2-01KRCDDC` (M-001 — Multi-Backend Storage with Revisions).
- Canonical spec: `kitty-specs/entity-storage-v2-01KRCDDC/spec.md` (also at `docs/specs/entity-storage-v2.md`).
- Plan: `kitty-specs/entity-storage-v2-01KRCDDC/plan.md`.
- Research + decisions: `kitty-specs/entity-storage-v2-01KRCDDC/research.md`.
- Data model: `kitty-specs/entity-storage-v2-01KRCDDC/data-model.md`.
- Normative contracts: `kitty-specs/entity-storage-v2-01KRCDDC/contracts/`.
- Charter §5.3 governs every stable-surface symbol introduced here.

### Dependencies
- `WP02` (must be done before this WP begins).

## Subtasks

### T018: Define lifecycle event classes

Create five files under `packages/entity-storage/src/Event/`:

- `EntityLifecycleEventInterface.php` — marker with `entity(): EntityInterface`.
- `BeforeSaveEvent.php` — adds `saveContext(): SaveContext`, `isNewRevision(): bool`.
- `AfterSaveEvent.php` — adds `saveContext(): SaveContext`, `isNewRevision(): bool`.
- `BeforeDeleteEvent.php` — basic.
- `AfterDeleteEvent.php` — basic.

All `final`, all `@api`. See `contracts/lifecycle-events.md` for full signatures.

**Files**: `packages/entity-storage/src/Event/*.php` (5 new files, ~30 lines each).

### T019: Define AbortOperationException

Create `packages/entity-storage/src/Event/AbortOperationException.php` extending `\RuntimeException`. Public readonly `$reason`, optional `$subscriberFqcn`.

**Files**: `packages/entity-storage/src/Event/AbortOperationException.php` (new, ~30 lines).

### T020: Define PartialSaveException

Create `packages/entity-storage/src/Exception/PartialSaveException.php` per `contracts/partial-save-error.md`. Public readonly `$entity`, `$causedBy`, `$committedBackends`, `$uncommittedBackends`, `$code = 'PARTIAL_SAVE'`. Message format includes counts and the underlying cause message.

**Files**: `packages/entity-storage/src/Exception/PartialSaveException.php` (new, ~45 lines).

### T021: Define SaveContext value object

Create `packages/entity-storage/src/SaveContext.php`. Private constructor; static `default()`; instance method `withoutNewRevision()` returns a NEW instance (immutable). Property `bool $withoutNewRevision` is the only state in this mission; future flags extend the constructor.

Resolves open question Q6 (research §3): dedicated value object, not flags array.

**Files**: `packages/entity-storage/src/SaveContext.php` (new, ~50 lines).

### T022: Dispatch lifecycle events in coordinator

Modify `packages/entity-storage/src/EntityStorageCoordinator.php` (owned by WP02).

Wire the optional `EventDispatcherInterface` from the WP02 constructor. Add a `CoordinatorLifecycleDispatcher` helper owned by THIS WP (`packages/entity-storage/src/CoordinatorLifecycleDispatcher.php`) that wraps the dispatcher calls.

Dispatch points (normative — `contracts/lifecycle-events.md`):
- `BeforeSaveEvent` before any backend write. Catch `AbortOperationException` → halt, propagate.
- `AfterSaveEvent` only after all writes succeed.
- `BeforeDeleteEvent` / `AfterDeleteEvent` symmetric.

**Files**: `packages/entity-storage/src/CoordinatorLifecycleDispatcher.php` (new, ~120 lines).

### T023: Implement partial-save semantics

In `CoordinatorLifecycleDispatcher` (T022) wrap backend fan-out:

- Track committed backend ids as each completes.
- On `\Throwable` from any backend: build uncommitted list (all backends not yet attempted + the failing one), throw `PartialSaveException` with `$committedBackends` and `$uncommittedBackends`.
- AfterSaveEvent / AfterDeleteEvent MUST NOT fire on partial failure.
- Emit a structured log line on the `entity.lifecycle` channel with `outcome=partial_save`.

**Files**: extends T022 file.

### T024: Integration tests for events and partial-save

Create `packages/entity-storage/tests/Integration/Events/`:

- Subscriber receives Before*/After* in correct order with correct payloads.
- Subscriber throwing `AbortOperationException` halts the operation; no AfterSave fires.
- Fake failing backend causes `PartialSaveException`; verify committed/uncommitted partition; AfterSave does NOT fire; log line emitted with expected structured fields.

**Files**: `packages/entity-storage/tests/Integration/Events/LifecycleDispatchTest.php`, `PartialSaveTest.php` (new, ~250 lines combined).

## Test Strategy

- Use PHPUnit 10.5 (project-mandated; **do not** pass `-v` — PHPUnit 10.5 rejects it).
- Unit tests under `packages/<pkg>/tests/Unit/`.
- Integration tests under `packages/<pkg>/tests/Integration/`.
- Contract tests under `packages/<pkg>/tests/Contract/` use `#[CoversNothing]`.
- In-memory storage for tests: `DBALDatabase::createSqlite()` (project gotcha — DBAL fetch mode is `fetchAssociative()`).
- Mock final classes with real instances + temp dirs (PHPUnit `createMock()` fails on `final class`).
- Log assertions: capture `Waaseyaa\Foundation\Log\LoggerInterface` via a recording fake; do not use `psr/log`.

## Definition of Done

- All subtasks (7) complete: T018, T019, T020, T021, T022, T023, T024.
- All requirement refs covered by tests: FR-020, FR-022, FR-023, FR-024, FR-025, FR-026, FR-027, FR-046, FR-047, FR-048.
- `composer cs-check` clean (run twice with cache cleared if needed per project gotcha).
- `composer phpstan` clean.
- `bin/check-package-layers` clean (no upward edges introduced).
- `bin/audit-dead-code` reports no new findings (mark intentional scaffolding with `@api`).
- `bin/check-composer-policy` clean (no `@dev`, no wildcard internal constraints, `self.version` only in root, internal `waaseyaa/*` constraints equal `^<current-tag>`).

## Risks

- **Partial-save semantics misimplemented**: AfterSave firing on partial failure would silently corrupt observability. Mitigation: explicit AfterSave-not-fired tests with failing-backend fake.
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
spec-kitty agent action implement WP04 --agent sonnet
```

## Review Command

```bash
spec-kitty agent action review WP04 --agent opus
```

Per mission agent assignments (mission.json): implementer = `sonnet`, reviewer = `opus`. Escalation target = `opus-as-implementer` after N=2 rejections.

## Activity Log

- 2026-05-12T01:51:37Z – claude:sonnet:implementer:implementer – shell_pid=416410 – Started implementation via action command
- 2026-05-12T02:00:12Z – claude:sonnet:implementer:implementer – shell_pid=416410 – Ready for review: lifecycle events + PartialSaveException (T018-T024)
- 2026-05-12T02:00:34Z – claude:opus:reviewer:reviewer – shell_pid=418808 – Started review via action command
- 2026-05-12T02:19:15Z – claude:sonnet:implementer:implementer – shell_pid=420549 – Started implementation via action command
- 2026-05-12T02:21:57Z – claude:sonnet:implementer:implementer – shell_pid=420549 – Cycle 3: retained $errorCode; updated contract + spec §6.5 with PHP constraint note — \Exception::$code cannot be typed in subclasses
- 2026-05-12T02:22:18Z – claude:opus:reviewer:reviewer – shell_pid=421645 – Started review via action command
- 2026-05-12T02:24:33Z – claude:opus:reviewer:reviewer – shell_pid=421645 – Cycle 4 approved: contract aligned with PHP constraint on Exception::$code
