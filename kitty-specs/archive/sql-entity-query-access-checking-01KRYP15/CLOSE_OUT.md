# Close-out: sql-entity-query-access-checking-01KRYP15

**Mission state at close-out:** 5/5 WPs `approved`, awaiting lane-archive flip.
**Actual implementation state:** Shipped.

## What happened

Mission shipped via squash-merge `4d9ca8a43 — feat(kitty/mission-sql-entity-query-access-checking-01KRYP15): squash merge of mission`. The mission's central runtime change — flipping `SqlEntityQuery::accessCheck(true)` to fail-closed by default — landed in v0.1.0-alpha.181. The 5 WPs progressed through `claimed → in_progress → for_review → approved` but never flipped to `done` because the squash-merge path does not auto-advance the lane state past `approved`.

Status.json `summary` at close-out:

```json
{"approved": 5, "blocked": 0, "canceled": 0, "claimed": 0, "done": 0,
 "for_review": 0, "in_progress": 0, "in_review": 0, "planned": 0}
```

This is the known `feedback_stuck_approved_mission_closeout.md` pattern: WPs land in `approved`, the squash-merge ships, but the lane never auto-advances. The archive step is manual.

## Post-merge fixes that proved the new gate worked

The fail-closed flip caught three closed-issue regressions in production:

- `#1518` (PathAliasResolver) — fixed in `8e809b619`
- `#1525` (AuthController::findUserByName) — fixed in `9dcb157f7`
- `#1527` (SitemapGenerator + UserBlockService) — fixed in `86b43f9cb`

Each was caught when production threw 500s after alpha.181. The pattern is documented in this mission's spec (or its plan) and the lessons-learned are captured in `feedback_internal_version_sweep_mechanism.md` and `feedback_release_split_pre_flight_gap.md`.

## Follow-up missions

- **M-B** (`access-fail-closed-completeness-01KS3RJT`) closes the three structural gaps this mission did not address: router-level account threading (#1516), policy auto-instantiation (#1519), CI gate against unbound `getQuery()` (#1528), and shared `RecordingEntityQuery` test helper (#1529). M-B also adds retro regression tests for #1518, #1525, #1527 so the prior fixes can't silently regress.

## Why archive now

This mission has no remaining work. All 5 WPs are `approved`. M-B explicitly cites this mission as its predecessor and continues the work. Archiving frees the active-missions list and removes a misleading "in progress" signal.

## References

- Mission spec: [spec.md](./spec.md)
- Squash-merge commit: `4d9ca8a43`
- Successor mission: `../access-fail-closed-completeness-01KS3RJT/`
- Post-merge fixes referenced above: `8e809b619`, `9dcb157f7`, `86b43f9cb`
- Audit date: 2026-05-20 (during backlog triage)
