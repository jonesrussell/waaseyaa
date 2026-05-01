# Plan: 584-perf-n-plus-one

Four phases mapped to WP02 → WP03 → WP04 (sequential read-path work) and WP05/WP06 in parallel. Phase boundaries are merge points; nothing crosses a phase line until the prior phase's WPs are `approved` per `docs/specs/workflow.md`.

## Phase 0 — Ratification (WP01 acceptance)

Objective: lock K1-K6 + C1-C3 and the cross-mission sequencing decision before any implementation WP starts.

- User ratifies K1-K4 (mostly mechanical conventions; auto-ratify likely).
- User ratifies K5 (eager-load return shape: wrapper bundle vs in-place hydration).
- User ratifies K6 (cluster-aggregation scope: partial fix vs `GROUP BY` rewrite).
- User ratifies C1 (union return type on `EntityRepository`).
- User ratifies C2 (new `IncludeBatcher` class).
- User ratifies C3 (`SqlEntityStorage` identity map).
- User picks Path Q1 (hold this mission until `#1257` merges) or Path Q2 (run in parallel with rebase pressure).

Exit criteria: choices recorded in `spec.md`. WP01 marked done. No code changes.

## Phase 1 — Verification gate (WP02)

Objective: produce `docs/specs/performance.md` with the canonical verification matrix.

- Five-row matrix (one per absorbed issue): live-source symbols, spec-doc location (or gap), disposition (DONE / GAP / PARTIAL / MIS-FILED), pointer to closing WP.
- `#587` declared DONE upfront (prevents `ReferenceLoader.php` duplication).
- `#586` re-target from `SqlEntityQuery` to `SqlEntityStorage` recorded (K2).
- K1-K6 + C1-C3 ratifications copied into the spec.
- Cross-mission sequencing (Path Q1/Q2 vs `#1257`) recorded.

Exit criteria: WP02 approved. Matrix merged. Future agents read it first.

## Phase 2 — Identity map (WP03)

Objective: ship `SqlEntityStorage` per-instance identity map. Prerequisite for WP04's includes batcher.

- Per-instance `array<int|string, EntityInterface> $identityMap` per C3.
- `load()` / `loadMultiple()` consult the map before SQL.
- `save()` / `delete()` invalidate (including failure paths — don't gate invalidation on success).
- Reconciled with existing `SqlEntityQuery::resultCache` — separate caches at separate layers, no overlap.
- K2 boundary enforced: no identity-map code reaches into `SqlEntityQuery`.
- Contract tests: `===` instance, post-save state visibility, post-delete null, failed-save invalidation.
- If Path Q2: rebase on `#1257` WP05 changes to `SqlEntityQuery::condition()` as they land.

Exit criteria: WP03 approved. Identity map shipping. Cache-hit observable via test.

## Phase 3 — Eager-load and JSON:API includes (WP04)

Objective: ship the user-facing N+1 fix. Depends on WP03 merging first.

- `EntityRepository::find()` / `findMany()` / `findBy()` accept `?array $include = null` per C1.
- Wrapper return shape per K5 ratified option.
- `Waaseyaa\Api\IncludeBatcher` per C2 (paired-nullable `?EntityAccessHandler` + `?AccountInterface`).
- `ResourceSerializer` collects `(target_type, target_id)` tuples during traversal.
- `JsonApiController::index()` / `show()` honor `?include=author,tags` query parameter.
- Field-access filtering on included resources via `EntityAccessHandler`.
- Sparse fieldsets per included type respected.
- Integration test: `?include=author` on 50-item collection produces ≤5 queries.

Exit criteria: WP04 approved. `?include=` works end-to-end. Integration test green.

## Phase 4 — Parallel: relationship pagination + DataLoader verification (WP05 + WP06)

Objective: close the remaining gaps. WP05 and WP06 may run in parallel after WP02 (independent surfaces).

- WP05: `RelationshipDiscoveryService` SQL pagination per K3. Cluster-aggregation per K6.
- WP06: `ReferenceLoader.php` verification + missing integration test. Mission-close note declares `#587` DONE.

Exit criteria: both WPs approved. Mission acceptance criteria met.

## Cross-phase invariants

- K2 boundary is non-negotiable: identity map lives on `SqlEntityStorage`, never `SqlEntityQuery`. Code review rejects any patch that violates this.
- `composer verify` (824 mission) gates every WP merge.
- No `psr/log`. Use `Waaseyaa\Foundation\Log\LoggerInterface` everywhere.
- Paired-nullable parameter convention (per `EntityAccessHandler` + `AccountInterface`): both non-null or both null. Negative-path tests required.
- `EntityBase` immutability preserved — `EntityIncludeBundle` wrapper return per K5 (a) instead of in-place mutation via setLoadedReference (K5 (b) rejected).

## Sequencing summary

```
WP01 (ratify) ─→ WP02 (matrix) ─→ WP03 (identity map) ─→ WP04 (includes)
                                       │
                                       ├─→ WP05 (relationship pagination)
                                       │
                                       └─→ WP06 (DataLoader verification)
```

WP03 → WP04 is the critical path. WP05 and WP06 parallelize. External dependency: `#1257` WP05 should merge before this mission's WP03 (Path Q1) or alongside with rebase (Path Q2).

## Post-mission cleanup (no WP required)

- Update `CLAUDE.md` orchestration table for new `docs/specs/performance.md`.
- Run `tools/drift-detector.sh` to confirm no Track 3 spec staleness.
- Verify `ReferenceLoader.php` has a comment pointing at `docs/specs/performance.md` (cross-link for future agents who might re-implement).
