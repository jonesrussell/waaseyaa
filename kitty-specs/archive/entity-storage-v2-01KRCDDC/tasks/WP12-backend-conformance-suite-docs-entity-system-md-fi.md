---
work_package_id: WP12
title: Backend-conformance suite + docs (entity-system.md, field-storage-backends.md)
dependencies:
- WP04
- WP06
- WP09
requirement_refs:
- FR-049
- FR-050
- FR-051
- FR-052
- FR-053
- FR-054
- FR-055
planning_base_branch: kitty/mission-entity-storage-v2-01KRCDDC
merge_target_branch: kitty/mission-entity-storage-v2-01KRCDDC
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-entity-storage-v2-01KRCDDC. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-entity-storage-v2-01KRCDDC unless the human explicitly redirects the landing branch.
subtasks:
- T063
- T064
- T065
- T066
- T067
- T068
- T069
agent: "claude:opus:reviewer:reviewer"
shell_pid: "490048"
history:
- timestamp: '2026-05-11T23:30:00+00:00'
  actor: claude
  action: wp_prompt_generated
  note: Generated from wps.yaml during tasks_packages step.
authoritative_surface: packages/entity-storage/testing/Contract
execution_mode: code_change
owned_files:
- packages/entity-storage/testing/Contract/FieldStorageBackendContractTestCase.php
- packages/entity-storage/tests/Contract/Backend/SqlBlobConformanceTest.php
- packages/entity-storage/tests/Contract/Backend/SqlColumnConformanceTest.php
- docs/specs/entity-system.md
- docs/specs/field-storage-backends.md
- docs/public-surface-map.md
- public-surface-map.php
tags: []
---

# WP12: Backend-conformance suite + docs (entity-system.md, field-storage-backends.md)

## Objective

Deliver the **backend-conformance suite + docs (entity-system.md, field-storage-backends.md)** scope of mission `entity-storage-v2-01KRCDDC` (M-001).

Requirement coverage: `FR-049`, `FR-050`, `FR-051`, `FR-052`, `FR-053`, `FR-054`, `FR-055`.

## Context

- Mission: `entity-storage-v2-01KRCDDC` (M-001 — Multi-Backend Storage with Revisions).
- Canonical spec: `kitty-specs/entity-storage-v2-01KRCDDC/spec.md` (also at `docs/specs/entity-storage-v2.md`).
- Plan: `kitty-specs/entity-storage-v2-01KRCDDC/plan.md`.
- Research + decisions: `kitty-specs/entity-storage-v2-01KRCDDC/research.md`.
- Data model: `kitty-specs/entity-storage-v2-01KRCDDC/data-model.md`.
- Normative contracts: `kitty-specs/entity-storage-v2-01KRCDDC/contracts/`.
- Charter §5.3 governs every stable-surface symbol introduced here.

### Dependencies
- `WP04` (must be done before this WP begins).
- `WP06` (must be done before this WP begins).
- `WP09` (must be done before this WP begins).

## Subtasks

### T063: FieldStorageBackendContractTestCase harness

Create `packages/entity-storage/testing/Contract/FieldStorageBackendContractTestCase.php`.

Important: NOT under `src/` because it extends `PHPUnit\Framework\TestCase` — autoload it via `autoload-dev` from `testing/`. (See CLAUDE.md gotcha: "Never put classes that extend dev-only deps under autoload".)

Abstract test class with template methods: `createBackend()`, `prepareFixtureEntity()`. Provides verified test methods for: id stability, read/write/delete round-trip, idempotent re-write, supportsQuery contract, delete cascade.

**Files**: new, ~220 lines.

### T064: Verify both backends pass conformance

Create `packages/entity-storage/tests/Contract/Backend/SqlBlobConformanceTest.php` and `SqlColumnConformanceTest.php`, each extending the harness from T063 with the appropriate `createBackend()` override.

**Files**: 2 new files, ~50 lines each.

### T065: Update entity-system.md

Add a "Field storage backends" section to `docs/specs/entity-system.md` covering: contract surface, registration, reserved-id rule, coordinator behavior, lifecycle events, revisions, per-revision access fallback rule.

**Files**: `docs/specs/entity-system.md` (modify; ~300 line addition).

### T066: Author field-storage-backends.md

New spec at `docs/specs/field-storage-backends.md`. Use the `contracts/field-storage-backend.md` document as the canonical source; flesh out with backend-implementer guidance (registration, idempotency, fail-fast at definition time, conformance test instructions).

**Files**: new, ~400 lines.

### T067: Final upgrade-guide canonicalization

Promote the WP11-seeded `docs/upgrades/waaseyaa-alpha-X-to-Y.md` to the final substrate-migration cookbook. Includes the operator runbook for partial-save recovery and the view_revision policy template.

**Files**: `docs/upgrades/waaseyaa-alpha-X-to-Y.md` (final form, ~500 lines).

### T068: Update public-surface-map entries

Add the new stable-surface symbols (interfaces, classes, exceptions, constants, CLI) to both `docs/public-surface-map.md` and `public-surface-map.php`. Tier: `stable`. Status: `present`. Mission: M-001.

Coordinate with the surface-map generator if one exists (see project conventions).

**Files**: `docs/public-surface-map.md` (modify), `public-surface-map.php` (modify).

### T069: Final mission acceptance review

Verify against spec §14 criteria:
1. All 12 WPs merged.
2. All §3 FRs covered by tests.
3. Conformance suite green for sql-blob and sql-column.
4. WP11 teaching migration in production 7 days no incident.
5. Charter §3.2 criterion 8 satisfiable.
6. public-surface-map entries reflected (stable, present).
7. First upgrade guide exists.

**Files**: acceptance checklist committed at `kitty-specs/entity-storage-v2-01KRCDDC/checklists/acceptance.md`.

## Test Strategy

- Use PHPUnit 10.5 (project-mandated; **do not** pass `-v` — PHPUnit 10.5 rejects it).
- Unit tests under `packages/<pkg>/tests/Unit/`.
- Integration tests under `packages/<pkg>/tests/Integration/`.
- Contract tests under `packages/<pkg>/tests/Contract/` use `#[CoversNothing]`.
- In-memory storage for tests: `DBALDatabase::createSqlite()` (project gotcha — DBAL fetch mode is `fetchAssociative()`).
- Mock final classes with real instances + temp dirs (PHPUnit `createMock()` fails on `final class`).
- Log assertions: capture `Waaseyaa\Foundation\Log\LoggerInterface` via a recording fake; do not use `psr/log`.

## Definition of Done

- All subtasks (7) complete: T063, T064, T065, T066, T067, T068, T069.
- All requirement refs covered by tests: FR-049, FR-050, FR-051, FR-052, FR-053, FR-054, FR-055.
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
spec-kitty agent action implement WP12 --agent sonnet
```

## Review Command

```bash
spec-kitty agent action review WP12 --agent opus
```

Per mission agent assignments (mission.json): implementer = `sonnet`, reviewer = `opus`. Escalation target = `opus-as-implementer` after N=2 rejections.

## Activity Log

- 2026-05-12T17:57:21Z – claude:sonnet:implementer:implementer – shell_pid=485998 – Started implementation via action command
- 2026-05-12T18:12:16Z – claude:sonnet:implementer:implementer – shell_pid=485998 – Ready for review: conformance harness + docs + acceptance checklist (T063-T069). Criterion 4 deferred per pending-minoo-cycle.md
- 2026-05-12T18:12:44Z – claude:opus:reviewer:reviewer – shell_pid=490048 – Started review via action command
- 2026-05-12T18:15:32Z – claude:opus:reviewer:reviewer – shell_pid=490048 – Cycle 1 approved: mission entity-storage-v2 complete (criterion 4 deferred to live Minoo cycle)
