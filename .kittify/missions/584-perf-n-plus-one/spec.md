# Mission spec: 584-perf-n-plus-one

**Charter:** Eliminate N+1 query patterns across the entity-storage / serialization read path. Implement JSON:API `?include=` with batched loading; add eager-load `include` parameter to `EntityRepository`; add per-request identity map to `SqlEntityStorage`; replace in-memory pagination in `RelationshipDiscoveryService` with SQL `LIMIT`/`OFFSET`; verify the GraphQL DataLoader pattern that already ships in `ReferenceLoader.php`.

**Milestone:** Track 3 — Parity & performance

**Origin:** Pass 1 architect-mode triage (2026-04-30). All 5 absorbed issues are CLOSED. Mission is reconciliation + targeted fills, same shape as `#589` and `#1257`.

**Decomposition artifact:** `decomposition.md` in this directory.

---

## Mission shape — verification + targeted fills

Live-source disposition of the 5 absorbed issues:

| Issue | Disposition |
|-------|-------------|
| `#584` JSON:API include batching (anchor) | **GAP.** `JsonApiController` does not implement `?include=` at all today — anchor body is misleading. Fix is "implement includes correctly with batching," not "fix existing N+1." |
| `#585` Eager-load on `EntityRepository` | **GAP.** Repository methods exist but no `include` parameter. New public surface (C1). |
| `#586` Query result caching on `SqlEntityQuery` | **PARTIAL / MIS-FILED.** `SqlEntityQuery::execute()` already has a result cache (line 351, ID-list cache). The issue actually wants an entity identity map by `(type, id)` — that belongs on `SqlEntityStorage::loadMultiple()`, not `SqlEntityQuery`. K2 enforces re-target. |
| `#587` GraphQL DataLoader | **DONE.** `packages/graphql/src/Resolver/ReferenceLoader.php` is a complete DataLoader (Deferred, per-type batching, dedup, depth bound). WP05 verifies and adds the missing integration test. Any agent re-implementing will collide. |
| `#588` SQL pagination in `RelationshipDiscoveryService` | **GAP.** `array_slice` at lines 38, 152, 237 with `count($edges)` for total. Mechanical fix to SQL `LIMIT`/`OFFSET`. Cluster aggregation path (line 68) is partial-fix — still loads all edges to build clusters in PHP. K6 ratifies scope. |

**Mode: mechanical-with-architectural-asterisk (1257/589 pattern).** Three new public surfaces (C1 union return on `EntityRepository`, C2 `IncludeBatcher` class, C3 `SqlEntityStorage` identity map) ride alongside mostly mechanical fixes.

---

## Decision: NO-SPLIT (5 WPs after decomposition)

Eager-load API (#585) and JSON:API includes (#584) cannot ship apart — the includes batcher consumes the eager-load API; the eager-load API has no other consumer in this mission. Identity map (#586) sits beneath both — every `loadMultiple()` call inside the includes batcher should hit the identity map first. Relationship pagination (#588) is structurally independent at the file level but conceptually inside the same "Audit §12" performance bundle. DataLoader verification (#587) could ship standalone but would be one-WP overhead.

| WP | Title | Outcome | Issues |
|----|-------|---------|--------|
| WP02 | verification-matrix-and-spec-gap-closure | New file `docs/specs/performance.md`. Verification matrix mapping each issue's acceptance criteria to live-source state. K2 re-target of #586 confirmed. K1-K6 + C1-C3 ratifications recorded. No code changes. | All 5 |
| WP03 | sqlentitystorage-identity-map | Per-instance `array<int\|string, EntityInterface> $identityMap` on `SqlEntityStorage`. Populated by `load()` / `loadMultiple()`; invalidated on `save()` / `delete()`. Reconciled with existing `SqlEntityQuery::resultCache` (no overlap; separate caches at separate layers). Contract test: two `load($id)` calls in same request return the same instance (`===`). | #586 |
| WP04 | eager-load-include-and-jsonapi-batcher | `?array $include = null` on `EntityRepository::find()` / `findMany()` / `findBy()` per C1. `Waaseyaa\Api\IncludeBatcher` class per C2. `JsonApiController::index()` / `show()` honor `?include=`; field-access filtering on included resources. Integration test: `?include=author` on 50-item collection produces ≤5 queries. | #584, #585 |
| WP05 | relationship-sql-pagination | Replace `array_slice` at lines 38, 152, 237 with `EntityQueryInterface::range($offset, $limit)` + separate `COUNT(*)` for total. Preserve existing response shape (`items`/`clusters` + `page` block). Cluster-aggregation path scope per K6. Memory-bounded test on 10k synthetic edges. | #588 |
| WP06 | dataloader-verification-and-missing-test | Read-only verification of `ReferenceLoader.php` against #587 acceptance. Add the missing integration test (20 nodes with author refs → 2 queries, not 21). Mission-close note declares #587 DONE. | #587 |

WP01 was the decomposition.

**Sequencing.** WP02 first (ratification gate). WP03 next (identity map is prerequisite for WP04's includes batcher to hit cache on duplicate references). WP04 after WP03. WP05 and WP06 may run in parallel with WP04 after WP02 (touch disjoint files).

Per-WP detail in `tasks.md`.

---

## Cross-mission sequencing — RATIFIED Path Q1 (2026-04-30)

`#1257` mission's WP05 rewrites `SqlEntityQuery::condition()` for cast-aware `_data` value coercion. This mission's WP03 adds an identity map to `SqlEntityStorage` (NOT `SqlEntityQuery` — K2 enforces the boundary).

**Decision: Path Q1.** This mission holds until `#1257` merges. Linear order, no rebase risk. WP03 starts only after `#1257` mission accepts. K2's "identity map stays on `SqlEntityStorage`" remains the load-bearing convention — code review rejects any patch that reaches into `SqlEntityQuery` regardless.

---

## Ratified conventions (K1-K6) — approved 2026-04-30

K1-K4 batch-ratified as accepted conventions. K5 ratified as option (a) wrapper return shape. K6 ratified as option (a) partial fix. Choices recorded inline.

### K1 — Verification-before-build is the mission's primary discipline — RATIFIED

Same as `#589` K1. WP02 produces the verification matrix. No code lands until the matrix is signed off. Particularly important here because `#587` ships fully — any agent that implements `EntityDataLoader` from scratch creates a duplicate of `ReferenceLoader`.

### K2 — Identity map lives at `SqlEntityStorage`, not `SqlEntityQuery` — RATIFIED

`#586` mis-files the location. `SqlEntityQuery::execute()` already has a result cache (line 351) for ID lists by query fingerprint. The identity map the issue wants is hydrated entities by `(entity_type_id, id)` — that's a `SqlEntityStorage::loadMultiple()` concern.

**Convention:** the per-request identity map is owned by `SqlEntityStorage`. Caches hydrated entities by `(entity_type_id, id)`. Returns the cached instance on subsequent `load()` / `loadMultiple()`. Invalidated on `save()` / `delete()`. Issue `#586` is re-targeted from `SqlEntityQuery` to `SqlEntityStorage`. This convention extends `#1257`'s K3 (cast-aware reads) into the identity-resolution boundary.

### K3 — Pagination total comes from a separate `COUNT(*)` query — RATIFIED

`JsonApiController::index()` and `EntityResolver::resolveList()` already do this correctly. `RelationshipDiscoveryService` must adopt the same pattern. **Convention:** any list endpoint returning a `total` computes it via a SQL `COUNT(*)` with the same `WHERE` clause as the main query, not by counting an in-memory array. Wired through `EntityQueryInterface::count()`.

### K4 — `?include=` on JSON:API is a two-pass collect-then-batch-load — RATIFIED

Pass 1 serializes the primary collection, collecting `(target_type, target_id)` tuples from every `entity_reference` field in the requested includes. Pass 2 groups by target type, runs one `loadMultiple()` per type, hydrates, attaches to JSON:API `included`.

**Convention:** the includes traversal is bounded by the `?include=author,tags` query parameter (no implicit deep loading), respects sparse fieldsets per type, runs through the same field-access filter as the primary serialization. Reuses the **pattern** from `ReferenceLoader` but **does not share the class** — JSON:API doesn't need GraphQL's `Deferred` machinery. Plain `IncludeBatcher` is enough.

### K5 — Eager-load `include` parameter shape on `EntityRepository` — RATIFIED option (a)

**Decision: Option (a).** Wrapper return shape `EntityIncludeBundle { entities, included }`. When `$include === null`, return shape unchanged (BC preserved). When non-null, return the bundle. Preserves `EntityBase` immutability per existing contract. Option (b) (in-place hydration via `setLoadedReference`) explicitly rejected — mutating parent entities breaks the immutability invariant the framework depends on.

### K6 — `RelationshipDiscoveryService` cluster-aggregation scope — RATIFIED option (a)

**Decision: Option (a) partial fix.** WP05 ships SQL `LIMIT`/`OFFSET` for the edge-list and discovery paths (lines 38, 152, 237). Cluster-aggregation path (lines 68-141) continues to build clusters in PHP after fetching all edges — the partial-fix limitation is documented explicitly in `docs/specs/performance.md`, with a "future work: GROUP BY rewrite" note for follow-up. Memory pressure on the cluster path is preserved and acknowledged. Cluster paths are not the current hot path; if profiling later contradicts that, a follow-up mission moves the aggregation to SQL.

---

## Ratified contracts (C1, C2, C3) — approved 2026-04-30

All three contracts ratified. Union return type on `EntityRepository`. New `IncludeBatcher` class. `SqlEntityStorage` per-request identity map.

### C1 — `EntityRepository::find/findMany/findBy` gain `?array $include = null` — RATIFIED (union return)

```php
public function find(string $id, ?string $langcode = null, bool $fallback = false, ?array $include = null): EntityInterface|EntityIncludeBundle|null;
public function findMany(array $ids, ?string $langcode = null, bool $fallback = false, ?array $include = null): array|EntityIncludeBundle;
public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?array $include = null): array|EntityIncludeBundle;
```

When `$include === null`, return shape unchanged (BC). When non-null, return `EntityIncludeBundle { entities, included: array<string, list<EntityInterface>> }`.

**Decision: union return type.** Separate `findManyWithIncludes()` methods rejected — duplication of three method signatures for one parameter shape isn't worth the type-system clarity tradeoff. The union matches the JSON:API mental model and gets documented inline in `docs/specs/performance.md`.

### C2 — `Waaseyaa\Api\IncludeBatcher` (new public class) — RATIFIED

Single-pass collection during `ResourceSerializer` traversal, single batch-load per target type via `EntityStorageInterface::loadMultiple()`, output as JSON:API `included`. Per-request lifetime, constructed by `JsonApiController` when `?include=` is non-empty. Constructor accepts `EntityTypeManagerInterface` + nullable `EntityAccessHandler` + nullable `AccountInterface` (paired-nullable convention).

### C3 — `SqlEntityStorage` per-request identity map — RATIFIED

Private `array<int|string, EntityInterface> $identityMap` (per-instance, per-entity-type). Internal contract — not a new public surface. Behavioral contract: two `load($id)` calls in same request return the **same instance** (`===`). Documented in `docs/specs/entity-system.md` §Identity & caching.

---

## Drift flags

| # | Flag | Resolution |
|---|------|------------|
| D1 | Anchor `#584` acceptance is misleading — implies includes are producing 50+ queries; reality is includes aren't implemented at all. | Spec reframes as "implement includes correctly with batching." |
| D2 | `#586` mis-files the location to `SqlEntityQuery`; identity map belongs on `SqlEntityStorage`. | K2 enforces re-target. |
| D3 | `#587` is already DONE in `ReferenceLoader.php`. | WP06 verifies; mission-close note declares DONE. |
| D4 | Overlap with `#1257` WP05 on `SqlEntityQuery`. | K2 enforces boundary (identity map stays on `SqlEntityStorage`). Sequencing decision (Path Q1 vs Q2 above). |
| D5 | `#588` cluster-aggregation memory pressure not solved by SQL pagination. | K6 ratifies scope (partial fix or `GROUP BY` rewrite). |

---

## Acceptance

The mission accepts when ALL of:

1. K1-K6 + C1-C3 ratified by user; choices recorded in `spec.md`.
2. `docs/specs/performance.md` exists with verification matrix, identity-map ownership (K2), include-batcher pattern (K4), eager-load `include` parameter shape (K5), cluster-aggregation scope (K6).
3. `SqlEntityStorage` ships per-instance identity map per C3; `===` contract test passes; save/delete invalidation contract test passes.
4. `EntityRepository::find()` / `findMany()` / `findBy()` accept `?array $include = null` per C1; wrapper return shape per K5; BC preserved when `$include === null`.
5. `Waaseyaa\Api\IncludeBatcher` ships per C2; `JsonApiController::index()` / `show()` honor `?include=`; integration test asserts `?include=author` on 50-item collection produces ≤5 queries.
6. `RelationshipDiscoveryService` paginates via SQL `LIMIT`/`OFFSET` + separate `COUNT(*)`; response shape preserved; memory-bounded test on 10k edges passes.
7. `ReferenceLoader.php` verified against `#587` acceptance; missing integration test added; mission-close note declares `#587` DONE.
8. All 5 absorbed issues remain closed; no re-opens.
9. `bin/check-package-layers`, `composer phpstan`, `composer cs-check`, `composer check-composer-policy` green.
10. `#1257` merge-first sequencing honored per Path Q1 (or Q2 with documented rebases per Path Q2).

---

## Risks

1. **WP03 identity map breaks save-cycle invariants if invalidation is wrong.** A stale entity in the identity map after save is harder to debug than the missing cache. Mitigation: invalidate on every `save()` / `delete()` including failures (don't gate on success); contract test verifies post-save loads observe new state.
2. **WP04 doubles JSON:API serialization time without short-circuiting.** Mitigation: WP03 identity map is a prerequisite — included entities loaded as part of primary collection serve from the map without a second SQL hit.
3. **C1 union return type may be controversial.** Some reviewers prefer separate `findManyWithIncludes()` methods. Surface in WP02 ratification. Planner: union accepted.
4. **`ReferenceLoader` lifetime assumption (per-request) is documented but not enforced.** Long-running CLI processes reusing the instance leak buffers. Mitigation: WP06 adds lifecycle test or a warning in `docs/specs/performance.md`.
5. **`#588` `total` field locks in `COUNT(*)` on every paginated request.** For very large relationship graphs `COUNT(*)` is itself a perf concern. Out of scope; flag for future "approximate or deferred totals" follow-up.
6. **Cross-mission collision with `#1257`.** Mitigated by K2 boundary + Path Q1/Q2 sequencing decision.
