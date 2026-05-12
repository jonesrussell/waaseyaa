---
work_package_id: WP09
title: Per-revision access (view_revision op + fallback)
dependencies:
- WP08
requirement_refs:
- FR-038
- FR-039
- FR-040
planning_base_branch: kitty/mission-entity-storage-v2-01KRCDDC
merge_target_branch: kitty/mission-entity-storage-v2-01KRCDDC
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-entity-storage-v2-01KRCDDC. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-entity-storage-v2-01KRCDDC unless the human explicitly redirects the landing branch.
subtasks:
- T047
- T048
- T049
- T050
agent: "claude:opus:reviewer:reviewer"
shell_pid: "455722"
history:
- timestamp: '2026-05-11T23:30:00+00:00'
  actor: claude
  action: wp_prompt_generated
  note: Generated from wps.yaml during tasks_packages step.
authoritative_surface: packages/access/src/Gate
execution_mode: code_change
owned_files:
- packages/access/src/Gate/GateInterface.php
- packages/access/src/Gate/Op.php
- packages/access/src/Gate/RevisionAccessRouter.php
- packages/access/tests/Integration/ViewRevision/**
tags: []
---

# WP09: Per-revision access (view_revision op + fallback)

## Objective

Deliver the **per-revision access (view_revision op + fallback)** scope of mission `entity-storage-v2-01KRCDDC` (M-001).

Requirement coverage: `FR-038`, `FR-039`, `FR-040`.

## Context

- Mission: `entity-storage-v2-01KRCDDC` (M-001 — Multi-Backend Storage with Revisions).
- Canonical spec: `kitty-specs/entity-storage-v2-01KRCDDC/spec.md` (also at `docs/specs/entity-storage-v2.md`).
- Plan: `kitty-specs/entity-storage-v2-01KRCDDC/plan.md`.
- Research + decisions: `kitty-specs/entity-storage-v2-01KRCDDC/research.md`.
- Data model: `kitty-specs/entity-storage-v2-01KRCDDC/data-model.md`.
- Normative contracts: `kitty-specs/entity-storage-v2-01KRCDDC/contracts/`.
- Charter §5.3 governs every stable-surface symbol introduced here.

### Dependencies
- `WP08` (must be done before this WP begins).

## Subtasks

### T047: Add view_revision op constant

Modify `packages/access/src/Gate/GateInterface.php` (or `Op.php` if op constants live there). Add `public const VIEW_REVISION = 'view_revision';`.

**Files**: minor delta (~5 lines).

### T048: Wire PolicyAttribute to accept view_revision

Modify `packages/access/src/Gate/PolicyAttribute.php` reflection routing: when `operations` includes `view_revision`, expect the policy class to declare `viewRevision(EntityInterface $entity, AccountInterface $account, RevisionMetadata $revision): AccessResult`. Missing method on a class that declared the op = boot-time failure.

**Files**: modify PolicyAttribute + attribute scanner; ~40 line delta.

### T049: Implement RevisionAccessRouter with fallback

Create `packages/access/src/Gate/RevisionAccessRouter.php`.

- Resolve the policy for the entity type. If the policy declares `view_revision`, route to `viewRevision()`.
- If undeclared, fall back to `view()`. Framework MUST NOT default-deny (`contracts/revisionable-entity.md` §11.2).
- Emit a structured log line on the `entity.lifecycle` channel with `outcome=view_revision_fallback` so observability captures unintended gaps.

**Files**: new, ~110 lines.

### T050: Access tests for view_revision

Cover three cases:
- Policy declares `view_revision` → custom rule wins.
- Policy does NOT declare → falls back to `view()`; log line emitted.
- Anonymous + non-public entity → fallback returns forbidden iff `view` returns forbidden.

**Files**: `packages/access/tests/Integration/ViewRevision/RouterTest.php` (new, ~180 lines).

## Test Strategy

- Use PHPUnit 10.5 (project-mandated; **do not** pass `-v` — PHPUnit 10.5 rejects it).
- Unit tests under `packages/<pkg>/tests/Unit/`.
- Integration tests under `packages/<pkg>/tests/Integration/`.
- Contract tests under `packages/<pkg>/tests/Contract/` use `#[CoversNothing]`.
- In-memory storage for tests: `DBALDatabase::createSqlite()` (project gotcha — DBAL fetch mode is `fetchAssociative()`).
- Mock final classes with real instances + temp dirs (PHPUnit `createMock()` fails on `final class`).
- Log assertions: capture `Waaseyaa\Foundation\Log\LoggerInterface` via a recording fake; do not use `psr/log`.

## Definition of Done

- All subtasks (4) complete: T047, T048, T049, T050.
- All requirement refs covered by tests: FR-038, FR-039, FR-040.
- `composer cs-check` clean (run twice with cache cleared if needed per project gotcha).
- `composer phpstan` clean.
- `bin/check-package-layers` clean (no upward edges introduced).
- `bin/audit-dead-code` reports no new findings (mark intentional scaffolding with `@api`).
- `bin/check-composer-policy` clean (no `@dev`, no wildcard internal constraints, `self.version` only in root, internal `waaseyaa/*` constraints equal `^<current-tag>`).

## Risks

- **Default-deny of view_revision** would break legacy policies on first deploy. Mitigation: explicit fallback rule + log line; integration test asserts fallback path.
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
spec-kitty agent action implement WP09 --agent sonnet
```

## Review Command

```bash
spec-kitty agent action review WP09 --agent opus
```

Per mission agent assignments (mission.json): implementer = `sonnet`, reviewer = `opus`. Escalation target = `opus-as-implementer` after N=2 rejections.

## Activity Log

- 2026-05-12T15:17:28Z – claude:sonnet:implementer:implementer – shell_pid=453194 – Started implementation via action command
- 2026-05-12T15:25:07Z – claude:sonnet:implementer:implementer – shell_pid=453194 – Ready for review: view_revision access router with fallback (T047-T050)
- 2026-05-12T15:25:46Z – claude:opus:reviewer:reviewer – shell_pid=455722 – Started review via action command
- 2026-05-12T15:28:23Z – claude:opus:reviewer:reviewer – shell_pid=455722 – Cycle 1 approved: view_revision op + RevisionAccessRouter fallback; no default-deny; full suite 7687/7687
