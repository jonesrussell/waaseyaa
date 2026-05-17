---
work_package_id: WP11
title: First Minoo entity migration (validation) + upgrade-guide pilot
dependencies:
- WP10
requirement_refs:
- FR-056
planning_base_branch: kitty/mission-entity-storage-v2-01KRCDDC
merge_target_branch: kitty/mission-entity-storage-v2-01KRCDDC
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-entity-storage-v2-01KRCDDC. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-entity-storage-v2-01KRCDDC unless the human explicitly redirects the landing branch.
subtasks:
- T057
- T058
- T059
- T060
- T061
- T062
agent: "claude:opus:reviewer:reviewer"
shell_pid: "471601"
history:
- timestamp: '2026-05-11T23:30:00+00:00'
  actor: claude
  action: wp_prompt_generated
  note: Generated from wps.yaml during tasks_packages step.
authoritative_surface: docs/upgrades
execution_mode: code_change
owned_files:
- docs/upgrades/waaseyaa-alpha-X-to-Y.md
- docs/upgrades/README.md
- kitty-specs/entity-storage-v2-01KRCDDC/validation/**
tags: []
---

# WP11: First Minoo entity migration (validation) + upgrade-guide pilot

## Objective

Deliver the **first minoo entity migration (validation) + upgrade-guide pilot** scope of mission `entity-storage-v2-01KRCDDC` (M-001).

Requirement coverage: `FR-056`.

## Context

- Mission: `entity-storage-v2-01KRCDDC` (M-001 — Multi-Backend Storage with Revisions).
- Canonical spec: `kitty-specs/entity-storage-v2-01KRCDDC/spec.md` (also at `docs/specs/entity-storage-v2.md`).
- Plan: `kitty-specs/entity-storage-v2-01KRCDDC/plan.md`.
- Research + decisions: `kitty-specs/entity-storage-v2-01KRCDDC/research.md`.
- Data model: `kitty-specs/entity-storage-v2-01KRCDDC/data-model.md`.
- Normative contracts: `kitty-specs/entity-storage-v2-01KRCDDC/contracts/`.
- Charter §5.3 governs every stable-surface symbol introduced here.

### Dependencies
- `WP10` (must be done before this WP begins).

## Subtasks

### T057: Generate Minoo teaching migration

In the Minoo working copy (`/home/jones/dev/minoo`):

```
bin/waaseyaa make:storage-migration teaching
```

Review the emitted migration for: all `teaching` fields present, indexed columns flagged (community_id, category_id, published_at), revision table emitted (teaching is revisionable in Minoo).

**Files**: Minoo-side migration file (lands in waaseyaa-minoo repo, not this one).

### T058: Apply migration in dev + staging

Apply migration via `bin/waaseyaa migrate` in Minoo dev. Verify:

- Schema matches expected (column types per §8.2).
- Backfilled data round-trips with no loss.
- Existing queries (by community, category, published) return identical results to pre-migration baseline.
- Revision table created; first new save creates a revision row.

Promote to staging; verify the same.

**Files**: validation log committed under `kitty-specs/entity-storage-v2-01KRCDDC/validation/teaching-migration-log.md`.

### T059: Annotate indexed fields in Minoo teaching

Modify Minoo's teaching `EntityType` definition (`waaseyaa-minoo` repo): add `FieldDefinition::indexed()` to community_id, category_id, published_at fields. Set `primaryStorageBackend: 'sql-column'`. Confirm `revisionable: true`.

**Files**: Minoo-side EntityType file.

### T060: Production rollout + 7-day monitoring

Roll teaching migration to production. Monitor for 7 days (spec §14 criterion 4). No related incidents = pass; any incident = mission stays open.

**Files**: production change log + monitoring report under `kitty-specs/entity-storage-v2-01KRCDDC/validation/production-monitoring.md`.

### T061: Capture WP11 lessons

Append a "Lessons from `teaching` migration" section to `docs/upgrades/waaseyaa-alpha-X-to-Y.md`: what surprised us, what the generator got right, what needs follow-up.

**Files**: `docs/upgrades/waaseyaa-alpha-X-to-Y.md` (modify; WP12 canonicalizes).

### T062: First concrete upgrade guide (FR-056)

Author the first concrete upgrade guide `docs/upgrades/waaseyaa-alpha-X-to-Y.md`. Numbering convention: substitute current and next alpha tag at release-cut time.

Contents: stable-surface deltas, sql-blob→sql-column migration recipe, revision opt-in steps, view_revision policy template, partial-save recovery patterns.

**Files**: `docs/upgrades/waaseyaa-alpha-X-to-Y.md` (new, ~250 lines).

## Test Strategy

- Use PHPUnit 10.5 (project-mandated; **do not** pass `-v` — PHPUnit 10.5 rejects it).
- Unit tests under `packages/<pkg>/tests/Unit/`.
- Integration tests under `packages/<pkg>/tests/Integration/`.
- Contract tests under `packages/<pkg>/tests/Contract/` use `#[CoversNothing]`.
- In-memory storage for tests: `DBALDatabase::createSqlite()` (project gotcha — DBAL fetch mode is `fetchAssociative()`).
- Mock final classes with real instances + temp dirs (PHPUnit `createMock()` fails on `final class`).
- Log assertions: capture `Waaseyaa\Foundation\Log\LoggerInterface` via a recording fake; do not use `psr/log`.

## Definition of Done

- All subtasks (6) complete: T057, T058, T059, T060, T061, T062.
- All requirement refs covered by tests: FR-056.
- `composer cs-check` clean (run twice with cache cleared if needed per project gotcha).
- `composer phpstan` clean.
- `bin/check-package-layers` clean (no upward edges introduced).
- `bin/audit-dead-code` reports no new findings (mark intentional scaffolding with `@api`).
- `bin/check-composer-policy` clean (no `@dev`, no wildcard internal constraints, `self.version` only in root, internal `waaseyaa/*` constraints equal `^<current-tag>`).

## Risks

- **Production rollout regression**. Mitigation: 7-day monitoring window; rollback plan documented in upgrade guide.
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
spec-kitty agent action implement WP11 --agent sonnet
```

## Review Command

```bash
spec-kitty agent action review WP11 --agent opus
```

Per mission agent assignments (mission.json): implementer = `sonnet`, reviewer = `opus`. Escalation target = `opus-as-implementer` after N=2 rejections.

## Activity Log

- 2026-05-12T17:08:48Z – claude:sonnet:implementer:implementer – shell_pid=465327 – Started implementation via action command
- 2026-05-12T17:16:22Z – claude:sonnet:implementer:implementer – shell_pid=465327 – Ready for review: upgrade guide (T062); T057-T061 deferred to live Minoo cycle per scope decision
- 2026-05-12T17:20:25Z – claude:opus:reviewer:reviewer – shell_pid=471601 – Started review via action command
- 2026-05-12T17:23:35Z – claude:opus:reviewer:reviewer – shell_pid=471601 – Cycle 1 approved: upgrade guide ships; operational T057–T061 tracked in pending-minoo-cycle.md
