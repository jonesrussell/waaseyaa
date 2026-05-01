# Decomposition — perf-n-plus-one

Date: 2026-04-30 (Pass 2 final-cohort output)

Mission charter: "Eager loading on EntityRepository, DataLoader for GraphQL, query result caching, SQL-level pagination, EntityReferenceItem N+1 fix."

Anchor: `#584` (CLOSED, 2026-04-30T01:04:26Z, label `track-3-parity-perf`). Track 3 — Parity & performance. Five issues total: `#584`, `#585`, `#586`, `#587`, `#588`. All five are CLOSED, all closed by the same triage pass. No open user-flagged keep.

## Mission summary

Mission #584 is a Track 3 aggregator over five "Audit §12" performance issues that all attack the same problem from different surfaces: N+1 queries during entity reference resolution (#584 JSON:API includes, #585 EntityRepository eager loading, #587 GraphQL DataLoader), in-request entity caching (#586 identity map on `SqlEntityQuery`), and pagination memory pressure (#588 RelationshipDiscoveryService). The grouping is real and tight — all five touch the read path between entity storage and serialization surfaces.

**Mode: mechanical-with-architectural-asterisk (1257/589 pattern).** The work is mostly hardening of existing query paths (`EntityRepository::findMany()`, `SqlEntityQuery::execute()`, `JsonApiController::index()`, `RelationshipDiscoveryService`), but two new public surfaces are involved: an `include` parameter on `EntityRepository::find()`/`findMany()`/`findBy()` (#585) and an `IncludeResolver`-style helper for JSON:API serialization (#584). One issue (#587) is **already implemented in shipping code** — `packages/graphql/src/Resolver/ReferenceLoader.php` is a DataLoader-style deferred batcher with per-type buffer flushing, exactly what #587 asks for. Verification-before-build is the primary discipline, same as #589.

The mission has a non-trivial architectural collision with `#1257` (entity-storage hardening) at the `SqlEntityQuery` boundary: #586's "identity map / per-request entity cache" overlaps with the result cache that already exists in `SqlEntityQuery::execute()` (`$this->resultCache->get($entityTypeId, $fingerprint)`). Two caches at two layers of the same class is a smell. WP02 must reconcile them or scope the identity map to `SqlEntityStorage::loadMultiple()` instead of `SqlEntityQuery`.

## Absorbed issues + anchor

| # | Title | State | Closed | Live-source disposition |
|---|---|---|---|---|
| 584 | perf: fix N+1 in EntityReferenceItem resolution (anchor) | CLOSED | 2026-04-30 | **GAP.** `ResourceSerializer.php` (206 lines) has no `?include=` handler. JSON:API includes are not implemented at all in the controller — `JsonApiController::index()` runs one `loadMultiple()` for the primary collection, but no second pass for referenced entities. The N+1 the issue describes only happens once includes ship; today the controller silently drops them. Two fixes intertwined: (a) implement JSON:API includes, (b) batch-load them. |
| 585 | perf: add eager loading support to EntityRepository | CLOSED | 2026-04-30 | **GAP.** `EntityRepository::find()`, `findMany()`, `findBy()` exist (lines 60, 89, 135) but accept only `(string $id, ?string $langcode, bool $fallback)` / `(array $ids, ?string $langcode, bool $fallback)` / `(array $criteria, ?array $orderBy, ?int $limit)`. No `include` parameter. New public-surface change. |
| 586 | perf: add query result caching to SqlEntityQuery | CLOSED | 2026-04-30 | **PARTIAL / DRIFTED.** `SqlEntityQuery::execute()` already calls `$this->resultCache->get($entityTypeId, $fingerprint)` (line 351). This is a query-result cache (stores ID lists by query fingerprint), not an entity identity map. The issue body asks for a per-request identity map keyed by `(entity_type, id)` returning hydrated entities. These are two different caches. **Issue is mis-scoped to the wrong file** — identity map belongs on `SqlEntityStorage::loadMultiple()` (or `EntityRepository::findMany()`), not `SqlEntityQuery`. |
| 587 | perf: add DataLoader pattern for GraphQL nested resolution | CLOSED | 2026-04-30 | **DONE.** `packages/graphql/src/Resolver/ReferenceLoader.php` (final class, 100+ lines) is a per-request DataLoader: `load(targetType, targetId, depth)` returns a `Deferred`, buffers by type, flushes via `loadMultiple()` per type, deduplicates with `array_unique`, respects `maxDepth=3`, runs field-access filtering via `GraphQlAccessGuard`. Constructed per request in `GraphQlEndpoint::handle`. Acceptance criteria are met. WP01 verifies, WP02 may add the missing integration test ("nested query produces O(depth) queries, not O(nodes)"). |
| 588 | perf: replace in-memory array pagination in RelationshipDiscoveryService | CLOSED | 2026-04-30 | **GAP.** Confirmed: `RelationshipDiscoveryService.php` uses `array_slice($edges, $offset, $limit)` at lines 38, 152, 237, with `count($edges)` for total. Three different paginated methods all do this. Fix is mechanical — push to SQL `LIMIT`/`OFFSET` via `Relationship` entity storage. |

## Conventions to ratify

### K1 — Verification-before-build is the mission's primary discipline (same as #589 K1)

WP01 produces a verification matrix: for each of the five issues, what already exists, what's missing, what's mis-scoped. No code lands until the matrix is signed off. #587 in particular ships fully — any agent that implements `EntityDataLoader` from scratch will collide with `ReferenceLoader` and create a duplicate. The matrix prevents that.

### K2 — Identity map lives at `SqlEntityStorage::loadMultiple()`, not `SqlEntityQuery::execute()`

`SqlEntityQuery::execute()` already has a result cache (line 351). It caches ID lists by query fingerprint. Adding an identity map to the same class would create two unrelated caches in one place and confuse the read path. **Convention:** the per-request identity map is owned by `SqlEntityStorage` (or its driver `SqlStorageDriver` when `EntityRepository` is in play). It caches **hydrated entities** by `(entity_type_id, id)`, returns the cached instance on subsequent `load()`/`loadMultiple()`, and is invalidated on `save()`/`delete()`. Issue #586's "File: `packages/entity-storage/src/SqlEntityQuery.php`" is wrong — re-target to `SqlEntityStorage`. This convention extends K3 from #1257 (cast-aware reads) into the identity-resolution boundary.

### K3 — Pagination total comes from a separate `COUNT` query, not `count(array)`

`JsonApiController::index()` and `EntityResolver::resolveList()` both already do this correctly: build a `$countQuery = $storage->getQuery()->accessCheck(false)`, apply filters only, call `->count()`, execute. `RelationshipDiscoveryService` must adopt the same pattern. **Convention:** any list endpoint that returns a `total` returns a value computed by a SQL `COUNT(*)` with the same `WHERE` clause as the main query, not by counting an in-memory array. Wire from `EntityQueryInterface::count()`.

### K4 — `?include=` on JSON:API is implemented as a two-pass collect-then-batch-load

Pass 1: serialize the primary collection, collecting `(target_type, target_id)` tuples from every `entity_reference` field in the requested includes. Pass 2: group by target type, run one `loadMultiple()` per type, hydrate, attach to JSON:API `included` array. **Convention:** the includes traversal is bounded by the JSON:API `?include=author,tags` query parameter (no implicit deep loading), respects sparse fieldsets per type, and runs through the same field-access filter as the primary serialization (`EntityAccessHandler` + `?AccountInterface`). Reuse the `ReferenceLoader` design pattern from `packages/graphql/src/Resolver/`, but **do not share the class** — JSON:API does not need GraphQL's `Deferred` machinery. A plain `IncludeBatcher` collected during one serialization pass is enough.

### K5 — Eager-load `include` parameter shape on `EntityRepository`

Issue #585 asks for `findBy(['status' => 'published'], include: ['author', 'tags'])`. The cleanest signature, consistent with `EntityRepository`'s existing `(?string $langcode, bool $fallback)` named arguments, is to add a third optional named parameter `?array $include = null` to `find()`, `findMany()`, `findBy()`. The implementation calls `loadMultiple()` for the primary set, walks `entity_reference` fields named in `$include`, collects target IDs grouped by target entity type, runs one `loadMultiple()` per target type, and **does not** mutate the parent entities (entities stay immutable per `EntityBase` contract); it returns the parent collection plus an out-parameter or wrapper structure carrying the loaded targets. **Decision required:** wrapper return shape (`['entities' => ..., 'included' => ['author' => [...], 'tags' => [...]]]`) vs in-place hydration via a new `EntityInterface::setLoadedReference()` API. Wrapper is simpler and preserves immutability; recommend wrapper.

### K6 — `RelationshipDiscoveryService` must own its own pagination contract, not leak `EntityQueryInterface`

The three paginated methods (lines 28, 68, 181 in `RelationshipDiscoveryService.php`) currently return `['items' => [...], 'page' => ['offset', 'limit', 'count', 'total']]` and `['clusters' => [...], 'page' => ...]`. The fix in #588 must preserve this exact response shape (the issue explicitly says "Existing API contract preserved"). **Convention:** the service holds its own `EntityQueryInterface` builder per discovery method and computes `total` via the same query without `range()`. The cluster-counting path (line 68) is harder — clusters are computed in PHP after fetching all edges. WP04 must decide whether cluster aggregation moves to SQL (`GROUP BY`) or accepts that "total clusters" requires fetching all edges first (memory pressure preserved on cluster path even after fix). Flag for ratification.

## SPLIT vs NO-SPLIT decision

Five issues, three subsystems, **shared read-path coupling**. Cleavage analysis:

| Cluster | Issues | Files touched |
|---|---|---|
| Eager-load API + JSON:API includes | 584, 585 | `EntityRepository`, `JsonApiController`, `ResourceSerializer`, new `IncludeBatcher` |
| Per-request identity map | 586 | `SqlEntityStorage` (re-targeted), `SqlEntityQuery` (decision boundary) |
| GraphQL DataLoader verification | 587 | `ReferenceLoader` (verify only) |
| Relationship SQL pagination | 588 | `RelationshipDiscoveryService`, `RelationshipTraversalService` |

Eager-load (585) and JSON:API includes (584) **must** ship together — the includes batcher consumes the eager-load API, and the eager-load API has no other consumer in this mission. Identity map (586) sits beneath both: every `loadMultiple()` call inside the includes batcher should hit the identity map first. Splitting eager-load + identity-map across missions would force one to merge and the other to re-derive against a moving `loadMultiple()` contract. The relationship pagination fix (588) is structurally independent at the file level but conceptually inside the same "Audit §12" performance bundle and small (one service, three methods).

The DataLoader verification (587) could ship as a one-WP standalone mission (verification + integration test), but extracting it adds mission-management overhead with no architectural benefit.

**NO-SPLIT.** One mission, four WPs. Same shape as #1257 and #589.

## Proposed WP roster

| WP | Title | Outcome | Issues |
|----|---|---|---|
| WP01 | Verification matrix + spec gap closure | Five-row table mapping each issue's acceptance criteria to live-source state. Confirms #587 is DONE; identifies #586's mis-scoping; confirms #584/585/588 are GAPs. Adds `docs/specs/performance.md` (or appends a §Performance section to `entity-system.md` and `jsonapi.md`) covering identity-map ownership (K2), include-batcher pattern (K4), eager-load `include` parameter shape (K5). Ratifies K1–K6. **No code changes.** | all 5 |
| WP02 | Identity map on `SqlEntityStorage` (#586 re-targeted) | Add per-instance `array<string, EntityInterface> $identityMap` to `SqlEntityStorage`, populated by `load()`/`loadMultiple()`, invalidated on `save()`/`delete()`. Reconcile with existing `SqlEntityQuery::resultCache` — separate caches at separate layers, no overlap. Unit tests verify cache hit/miss, save/delete invalidation, no leak across requests (storage is per-EntityType, kernel rebuilds per request). Decision recorded in spec re K2. | 586 |
| WP03 | Eager-load `include` on EntityRepository + JSON:API includes | Add `?array $include = null` named parameter to `EntityRepository::find()`, `findMany()`, `findBy()`. Wrapper return shape per K5. Implement `Waaseyaa\Api\IncludeBatcher` (collect during ResourceSerializer pass, batch-load grouped by target type via `loadMultiple()`, attach to JSON:API `included`). Wire `JsonApiController::index()` and `show()` to honor `?include=`. Field-access filtering on included resources. Integration test: `?include=author` on a 50-item collection produces ≤5 queries. | 584, 585 |
| WP04 | Relationship SQL pagination | Replace `array_slice` at lines 38, 152, 237 with `EntityQueryInterface::range($offset, $limit)`. Add separate `COUNT` query for `total`. Preserve response shape (`items`/`clusters` + `page` block). Document cluster-aggregation memory pressure if not moved to `GROUP BY` (open question for ratification). Memory-bounded test: paginate over 10k synthetic edges, assert constant memory. | 588 |
| WP05 | DataLoader verification + missing test (#587) | Read-only verification of `ReferenceLoader.php` against #587 acceptance criteria. Add the missing integration test: GraphQL query for 20 nodes with author refs produces 2 queries (1 for nodes, 1 for authors), not 21. Cross-link mission close note to issue #587 declaring DONE. | 587 |

Optional WP06 (deferred): pagination cursor support on `EntityQueryInterface` (issue body for #588 mentions "in-memory" only; cursor-vs-offset is out of scope here, flag for a follow-up mission if needed).

## PROPOSED CONTRACTS / CONVENTIONS — needs ratification

### C1 — `EntityRepository::find/findMany/findBy` gain `?array $include = null` and a wrapper return shape (#585)

**Surface:**

```php
public function find(string $id, ?string $langcode = null, bool $fallback = false, ?array $include = null): EntityInterface|EntityIncludeBundle|null;
public function findMany(array $ids, ?string $langcode = null, bool $fallback = false, ?array $include = null): array|EntityIncludeBundle;
public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?array $include = null): array|EntityIncludeBundle;
```

When `$include === null`, return shape is unchanged (BC). When `$include` is non-null, return `EntityIncludeBundle { entities: list<EntityInterface>, included: array<string, list<EntityInterface>> }`. **Decision required:** is the union return type acceptable, or should the `include`-aware path be a separate method (`findManyWithIncludes()`)? Recommend the union — it matches the JSON:API mental model and is documented inline.

### C2 — `Waaseyaa\Api\IncludeBatcher` (new class, public surface for #584)

Single-pass collection during `ResourceSerializer` traversal, single batch-load per target type via `EntityStorageInterface::loadMultiple()`, output as JSON:API `included` array. Contract sketch:

```php
final class IncludeBatcher {
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly ?EntityAccessHandler $accessHandler = null,
        private readonly ?AccountInterface $account = null,
    ) {}
    public function collect(string $targetType, int|string $targetId): void;
    public function flush(): array; // grouped by target type, access-filtered
}
```

Per-request lifetime, constructed by `JsonApiController` when `?include=` is non-empty.

### C3 — `SqlEntityStorage` per-request identity map (#586 re-targeted)

Private `array<int|string, EntityInterface> $identityMap` (per-instance, per-entity-type). `load()` / `loadMultiple()` consult it before SQL; `save()` / `delete()` invalidate the relevant ID. **Not** a new public surface — purely internal. Behavioral contract: two `load($id)` calls in the same request return the **same instance** (`===`). Documented in `docs/specs/entity-system.md` §Identity & caching.

## Drift flags

- **Anchor #584 acceptance is misleading.** "JSON:API `?include=author` on 50-item collection produces ≤5 queries (not 50+)" implies includes are currently producing 50+ queries. Live source: `JsonApiController` does not implement `?include=` at all. Today's count is "1 query, no includes returned." The fix is "implement includes correctly with batching" not "fix N+1 in existing includes path."
- **Issue #586 mis-files the location.** Body says `packages/entity-storage/src/SqlEntityQuery.php`. Live source: `SqlEntityQuery::execute()` already has a `resultCache` (line 351) that caches ID lists by query fingerprint. The identity map the issue actually wants is on `SqlEntityStorage::loadMultiple()`. WP01 must call this out and re-target before WP02 starts work; otherwise the identity map gets bolted onto the wrong class and conflicts with the existing result cache.
- **Issue #587 is already DONE.** `ReferenceLoader.php` matches every acceptance criterion (per-type batching, Deferred, `loadMultiple()` per flush, dedup, depth bound). Any agent that reads "implement DataLoader for GraphQL" without checking the live source will create a duplicate class. WP01 / WP05 must declare DONE, not re-implement.
- **Overlap with #1257 WP05 (cast-aware `_data` value coercion in query builder).** #1257's WP05 rewrites `SqlEntityQuery::condition()` for type coercion at the JSON-extract boundary. #584's WP02 (identity map) does not touch `SqlEntityQuery::condition()`, so no file-level collision — but if WP02 is allowed to drift into `SqlEntityQuery`, the two missions will collide on the same class. K2 enforces the boundary: identity map stays on `SqlEntityStorage`. **Sequence #1257 to merge first** if both are in flight; #584 should rebase on top of `SqlEntityQuery::condition()` changes.
- **#588 cluster-aggregation memory pressure is not solved by pushing pagination to SQL.** Lines 68–141 build clusters in PHP (`$clusters[$clusterKey]['count']++`). Even with SQL `LIMIT`/`OFFSET` on the underlying edges query, computing all clusters still requires fetching all edges. The fix is partial unless cluster aggregation moves to `GROUP BY`. Issue body does not acknowledge this. Flag for ratification — accept partial fix or expand scope.
- **No benchmark gate.** All five issues use words like "scales poorly" and "memory pressure" without stated thresholds. Acceptance criteria are query-count assertions, not latency or memory budgets. **Acceptable for this mission** (query count is a clean proxy), but `docs/specs/performance.md` should establish a "performance claim requires query-count or memory-budget evidence" convention for future issues.

## Risks

- **WP02 (identity map) breaks save-cycle invariants if invalidation is wrong.** A stale entity in the identity map after a save is harder to debug than the missing cache. Mitigation: invalidate on every `save()`/`delete()`, including failures (don't gate invalidation on success); add a contract test that verifies post-save loads observe the new state.
- **WP03 risks doubling JSON:API serialization time** if includes traversal is implemented as a synchronous second pass without short-circuiting on cache hits. Mitigation: identity map (WP02) is a prerequisite — included entities loaded as part of the primary collection should be served from the map without a second SQL hit.
- **C1 union return type may be controversial.** Some reviewers prefer separate `findManyWithIncludes()` methods over a union return. Surface in WP01 spec for ratification.
- **`ReferenceLoader` lifetime assumption (per-request)** is documented in source but not enforced — if a long-running CLI process reuses the same instance, buffers leak across operations. WP05 should add a lifecycle test (or a warning in `docs/specs/performance.md`) covering this.
- **#588's "preserve API contract" constraint** locks in the `total` field, which forces a `COUNT(*)` query on every paginated request. For very large relationship graphs this is itself a performance issue. Out of scope for this mission, but flag for a future "approximate or deferred totals" follow-up.
