# Archived: broadcasting-end-to-end-01KRW8YB

**Archived:** 2026-05-18
**Outcome:** Shipped via PR #1507 as deletion-only, closing #1497.
**Spec status:** Stale at filing.

## Why archived without going through the implement-review loop

The mission spec assumed the in-memory `SseBroadcaster` + `BroadcasterInterface`
+ `BroadcastMessage` + `BroadcastController` scaffold needed wiring. On
inspection, the production system already drove SSE via a different design:
`BroadcastStorage` (DBAL-backed log), `EventListenerRegistrar::registerBroadcastListeners`
(post_save / post_delete listeners), and `BroadcastRouter::handle` polling the
log. The in-memory broadcaster was never reachable from any production code
path; the 5 baseline entries documented its orphan status.

The mission converted to a deletion-only cleanup. WP01–WP06 from the original
outline were never materialized because the implementation work the spec assumed
was either (a) already shipped under different names, or (b) not actually
required.

## What shipped (PR #1507)

- Deleted: `BroadcasterInterface`, `BroadcastMessage`, `SseBroadcaster`,
  `BroadcastController` and their unit tests.
- Inlined `BroadcastController::parseChannels` into `BroadcastRouter`
  (sole caller).
- Dropped 5 `SseBroadcaster` entries from `phpstan-dead-code-baseline.neon`
  and one tangential entry from `phpstan-baseline.neon`.
- Removed the `BroadcasterInterface` entry from `docs/public-surface-map.php`.
- Added `docs/specs/broadcasting.md` documenting the production
  `BroadcastStorage` path.
- Updated `docs/specs/infrastructure.md`, `docs/specs/api-layer.md`, and
  `CLAUDE.md` orchestration to reflect reality.
- Updated `skills/waaseyaa/infrastructure/SKILL.md` (had described a
  non-existent `EventBus` taking a `BroadcasterInterface`).
- Added `tests/Integration/PhaseBroadcasting/BroadcastingE2ETest.php`
  covering the live publisher → log → poll path.

## Lessons (for the audit-staleness memory)

The spec was filed on 2026-05-17 ~20:49 without first inspecting how the
production system actually wired broadcasting. The audit pattern documented in
the `feedback_check_audit_followups` memory applies here: before drafting a
"complete the wiring" mission, grep the kernel for the relevant types and
follow `Closes #N` history on the issue.
