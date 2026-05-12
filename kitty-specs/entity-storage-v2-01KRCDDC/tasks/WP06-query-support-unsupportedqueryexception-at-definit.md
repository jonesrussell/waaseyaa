---
work_package_id: WP06
title: Query support + UnsupportedQueryException at definition validation
dependencies:
- WP03
- WP05
requirement_refs:
- FR-009
- FR-014
- FR-015
- FR-021
- FR-046
planning_base_branch: kitty/mission-entity-storage-v2-01KRCDDC
merge_target_branch: kitty/mission-entity-storage-v2-01KRCDDC
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-entity-storage-v2-01KRCDDC. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-entity-storage-v2-01KRCDDC unless the human explicitly redirects the landing branch.
subtasks:
- T031
- T032
- T033
- T034
agent: "claude:opus:reviewer:reviewer"
shell_pid: "439588"
history:
- timestamp: '2026-05-11T23:30:00+00:00'
  actor: claude
  action: wp_prompt_generated
  note: Generated from wps.yaml during tasks_packages step.
authoritative_surface: packages/entity-storage/src/Exception
execution_mode: code_change
owned_files:
- packages/entity-storage/src/Exception/UnsupportedQueryException.php
- packages/entity-storage/src/Exception/UnsupportedListingException.php
- packages/entity-storage/src/Query/DefinitionValidator.php
- packages/entity-storage/tests/Integration/Query/**
tags: []
---

# WP06: Query support + UnsupportedQueryException at definition validation

## Objective

Deliver the **query support + unsupportedqueryexception at definition validation** scope of mission `entity-storage-v2-01KRCDDC` (M-001).

Requirement coverage: `FR-009`, `FR-014`, `FR-015`, `FR-021`, `FR-046`.

## Context

- Mission: `entity-storage-v2-01KRCDDC` (M-001 — Multi-Backend Storage with Revisions).
- Canonical spec: `kitty-specs/entity-storage-v2-01KRCDDC/spec.md` (also at `docs/specs/entity-storage-v2.md`).
- Plan: `kitty-specs/entity-storage-v2-01KRCDDC/plan.md`.
- Research + decisions: `kitty-specs/entity-storage-v2-01KRCDDC/research.md`.
- Data model: `kitty-specs/entity-storage-v2-01KRCDDC/data-model.md`.
- Normative contracts: `kitty-specs/entity-storage-v2-01KRCDDC/contracts/`.
- Charter §5.3 governs every stable-surface symbol introduced here.

### Dependencies
- `WP03` (must be done before this WP begins).
- `WP05` (must be done before this WP begins).

## Subtasks

### T031: Define query support exceptions

- `packages/entity-storage/src/Exception/UnsupportedQueryException.php` — public readonly `$backendId`, `$fieldId`, `$reason`.
- `packages/entity-storage/src/Exception/UnsupportedListingException.php` — reserved for ADR 015 forward-compat; identical shape (`@api` annotated).

**Files**: two new files, ~35 lines each.

### T032: Implement DefinitionValidator

Create `packages/entity-storage/src/Query/DefinitionValidator.php`. Invoked at BOOT (after `BackendRegistrar` finishes), for every registered entity type:

- For each `FieldDefinition`, resolve its backend via `BackendResolver`.
- If the field has declared query needs (via `EntityType` declared queries or `FieldDefinition::indexed()`), call `$backend->supportsQuery($field, $query)`.
- If false, throw `UnsupportedQueryException`. Boot fails. NO query-time fallback.

**Files**: `packages/entity-storage/src/Query/DefinitionValidator.php` (new, ~130 lines).

### T033: Integration tests for query support

Two tests:
- Indexed field + supported operator → query succeeds.
- Non-indexed field on `sql-column` with operator the backend cannot satisfy → `UnsupportedQueryException` at boot, not at runtime.

**Files**: `packages/entity-storage/tests/Integration/Query/DefinitionValidatorTest.php` (new, ~150 lines).

### T034: Document fail-fast contract for WP12 spec

Append a section "Query support is checked at definition time" to a draft section for `docs/specs/field-storage-backends.md` (canonicalized in WP12).

**Files**: `kitty-specs/entity-storage-v2-01KRCDDC/contracts/field-storage-backend.md` — append a "Fail-fast guarantee" subsection.

## Test Strategy

- Use PHPUnit 10.5 (project-mandated; **do not** pass `-v` — PHPUnit 10.5 rejects it).
- Unit tests under `packages/<pkg>/tests/Unit/`.
- Integration tests under `packages/<pkg>/tests/Integration/`.
- Contract tests under `packages/<pkg>/tests/Contract/` use `#[CoversNothing]`.
- In-memory storage for tests: `DBALDatabase::createSqlite()` (project gotcha — DBAL fetch mode is `fetchAssociative()`).
- Mock final classes with real instances + temp dirs (PHPUnit `createMock()` fails on `final class`).
- Log assertions: capture `Waaseyaa\Foundation\Log\LoggerInterface` via a recording fake; do not use `psr/log`.

## Definition of Done

- All subtasks (4) complete: T031, T032, T033, T034.
- All requirement refs covered by tests: FR-009, FR-014, FR-015, FR-021, FR-046.
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
spec-kitty agent action implement WP06 --agent sonnet
```

## Review Command

```bash
spec-kitty agent action review WP06 --agent opus
```

Per mission agent assignments (mission.json): implementer = `sonnet`, reviewer = `opus`. Escalation target = `opus-as-implementer` after N=2 rejections.

## Activity Log

- 2026-05-12T13:41:18Z – claude:sonnet:implementer:implementer – shell_pid=437468 – Started implementation via action command
- 2026-05-12T13:48:09Z – claude:sonnet:implementer:implementer – shell_pid=437468 – Ready for review: query validator + fail-fast contract (T031-T034)
- 2026-05-12T13:48:37Z – claude:opus:reviewer:reviewer – shell_pid=439588 – Started review via action command
- 2026-05-12T13:50:33Z – claude:opus:reviewer:reviewer – shell_pid=439588 – Cycle 1 approved: definition-time query validation + UnsupportedQueryException; boot wire-up deferred to integration WP
