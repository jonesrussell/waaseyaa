# Tasks / work packages — 584-perf-n-plus-one

WP01 was the Pass-2 decomposition (`decomposition.md`). WP02 ratifies + matrix. WP03 identity map (prerequisite for WP04). WP04 eager-load + JSON:API includes. WP05 (relationship pagination) and WP06 (DataLoader verification) parallel after WP02.

| WP | Title | Outcome | Status |
|----|-------|---------|--------|
| WP01 | mission-decomposition | `decomposition.md`. Mode = mechanical-with-architectural-asterisk. NO-SPLIT. 6 conventions (K1-K6) + 3 contracts (C1-C3) surfaced. 5 drift flags (incl. cross-mission overlap with #1257). | done |
| WP02 | verification-matrix-and-spec-gap-closure | K1-K6 + C1-C3 already ratified in `spec.md` (2026-04-30). Path Q1 chosen: hold this mission until `#1257` merges. WP02 produces new `docs/specs/performance.md` with verification matrix mapping each issue's acceptance to live-source state (`#587` DONE; `#586` mis-scoped → re-targeted to `SqlEntityStorage` per K2; `#584`/`#585`/`#588` GAP). K6 partial-fix limitation explicitly documented with "future work: GROUP BY rewrite" note. No code changes. | unscheduled |
| WP03 | sqlentitystorage-identity-map | `Waaseyaa\EntityStorage\SqlEntityStorage` gains private `array<int\|string, EntityInterface> $identityMap` per C3. Populated by `load()` / `loadMultiple()`; invalidated on `save()` / `delete()` (including failure paths). Reconciled with existing `SqlEntityQuery::resultCache` — separate caches at separate layers, no overlap. K2 boundary enforced: no identity-map code reaches into `SqlEntityQuery`. Contract tests: (a) two `load($id)` calls in same request return `===` instance, (b) post-save `load()` observes new state, (c) post-delete `load()` returns null, (d) failed save still invalidates. | unscheduled |
| WP04 | eager-load-include-and-jsonapi-batcher | `EntityRepository::find()` / `findMany()` / `findBy()` accept `?array $include = null` per C1. Wrapper return shape per ratified K5. `Waaseyaa\Api\IncludeBatcher` per C2 (constructor: `EntityTypeManagerInterface` + `?EntityAccessHandler` + `?AccountInterface`). `ResourceSerializer` collects includes during traversal; `JsonApiController::index()` / `show()` flush via batcher when `?include=` non-empty. Field-access filtering on included resources. Integration test: `?include=author` on 50-item collection produces ≤5 queries. WP04 depends on WP03 (identity map cache hits prevent second SQL pass on duplicates). | unscheduled |
| WP05 | relationship-sql-pagination | Replace `array_slice` at lines 38, 152, 237 of `RelationshipDiscoveryService.php` with `EntityQueryInterface::range($offset, $limit)`. Add separate `COUNT(*)` query for `total` per K3. Preserve response shape (`items`/`clusters` + `page` block). Cluster-aggregation path (line 68) handled per ratified K6 (partial fix OR `GROUP BY` rewrite). Memory-bounded test: paginate over 10k synthetic edges, assert constant memory. | unscheduled |
| WP06 | dataloader-verification-and-missing-test | Read-only verification of `packages/graphql/src/Resolver/ReferenceLoader.php` against `#587` acceptance criteria. Add the missing integration test: GraphQL query for 20 nodes with author refs produces 2 queries (1 nodes + 1 authors), not 21. Lifecycle test or doc note covering "per-request lifetime; long-running CLI processes must construct fresh instances." Mission-close cross-link comment on `#587` declaring DONE. | unscheduled |

**Review gate:** Each WP runs through Spec Kitty implement → review per `docs/specs/workflow.md`. WP02 must reach `approved` before WP03-WP06 enter implement.

## Dependencies (between WPs and external missions)

- WP02 → WP03, WP04, WP05, WP06 (ratification gate; matrix is canonical state)
- WP03 → WP04 (identity map is prerequisite for includes batcher cache hits)
- WP04 ⟂ WP05 ⟂ WP06 (parallel after WP02 + WP03)
- **External: WP03 blocked on `#1257` mission acceptance** (Path Q1 ratified). This mission's WP03 starts only after `#1257` merges. K2 (identity map stays on `SqlEntityStorage`) remains the boundary regardless.

## Per-WP gating notes

- **WP02** is the discipline gate. Without the verification matrix, `#587` will be re-implemented (`ReferenceLoader.php` already ships) and `#586` will be bolted onto the wrong class (`SqlEntityQuery` instead of `SqlEntityStorage`).
- **WP03** must NOT reach into `SqlEntityQuery`. K2 is the load-bearing boundary. Code review rejects any patch that touches `SqlEntityQuery::execute()` or `SqlEntityQuery::condition()`.
- **WP04** depends on WP03 merging first — without the identity map, included entities loaded as part of the primary collection are re-fetched in the includes pass.
- **WP05** scope depends on K6: partial-fix path is ~2 hours of work; `GROUP BY` rewrite is ~1 day plus benchmark. WP02 records the chosen path.
- **WP06** is read-only. No code changes to `ReferenceLoader.php` itself; only an integration test added in `packages/graphql/tests/Integration/`.
