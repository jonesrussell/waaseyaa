# Contract: ListingResolver + ListingResult + Pagination

**Stability scope:** Charter §5.X
**FRs covered:** FR-018..FR-032
**Owned by:** WP05 (core resolver), WP06 (access policy application), WP07 (cache integration)

## ListingResolver

```php
final class ListingResolver
{
    public function __construct(
        private readonly EntityRepositoryRegistry $repositories,
        private readonly GateInterface $gate,
        private readonly TaggedCacheInterface $cache,
        private readonly ContextResolver $contextResolver,
        private readonly ListingCacheKeyBuilder $keyBuilder,
        private readonly EntityTypeManager $entityTypes,
        private readonly RequestContext $requestContext,
    );

    public function resolve(
        ListingDefinition $def,
        ?ExposedFilterValues $exposed = null,
    ): ListingResult;
}
```

**Stability commitment:** `resolve()` signature is stable. Constructor dependencies are injection-only; consumers do not instantiate `ListingResolver` directly (it's a DI-bound service).

## Resolution algorithm (FR-019, §7.1 normative)

1. `$exposed = $exposed ?? new ExposedFilterValues()`.
2. `$contextValues = []`; for each `$ctx` in `$def->effectiveContexts($entityType)`: `$contextValues[$ctx] = $this->contextResolver->resolve($ctx, $this->requestContext)`. Skip caching if any context returns unknown (per R-11 / FR-035).
3. `$key = $this->keyBuilder->build($def, $exposed, $contextValues)`.
4. `$cached = $this->cache->get($key)`. If non-null `ListingResult`, return it.
5. Build `EntityQuery` from `$def->filters` + `$def->sorts` + implicit `id`-tie-break sort + implicit langcode filter (if translatable + no explicit langcode in declared filters) + `?page=N` offset/limit per `ExposedFilterValues`.
6. `$rawRows = $query->execute()` — list of entity instances.
7. `$accessRows = []`. For each `$row` in `$rawRows`: if all `$op` in `$def->accessOps` return non-`Forbidden` from `$this->gate->access(...)`, append. (FR-032 fast-path: when all bound policies expose `SUPPORTS_LISTING_FAST_PATH = true`, skip the per-row loop.)
8. Build `Pagination`. If `!$def->approximateTotal`: re-run steps 5–7 over the unpaginated query to compute `$totalRows`. If `$def->approximateTotal === true`: `$totalRows = null`, `$totalPages = null`.
9. Build `$cacheTags` per FR-023:
   - `entity:<type>` (always)
   - `entity:<type>:<id>` per row in `$accessRows`
   - `entity:<type>:<id>:<langcode>` per row when translatable (langcode = `$row->activeLangcode()`)
10. Build `$cacheContexts` per FR-024 (the `effectiveContexts()` output from step 2).
11. `$result = new ListingResult($accessRows, $pagination, $cacheTags, $cacheContexts)`.
12. If steps 2 didn't bypass cache: `$this->cache->setWithTags($key, $result, $cacheTags, $def->cacheTtl)`.
13. Return `$result`.

**Determinism:** Same `(def, exposed, requestContext)` always produces the same result modulo cache state. The cache is the only source of state.

**Error model:**
- Throws `UnknownListingException` only if a caller bypasses the registry — `resolve()` itself accepts the definition by-value, so the throw site is the registry.
- Storage-backend errors propagate as-is (already typed by backend).
- Cache-backend errors caught + logged + resolution continues without caching (FR-058).
- Per-row access check failures filter the row silently (FR-029).

## ListingResult

```php
final readonly class ListingResult
{
    public function __construct(
        public iterable $rows,                  // iterable<EntityInterface>
        public Pagination $pagination,
        public array $cacheTags,                // list<non-empty-string>
        public array $cacheContexts,            // list<non-empty-string>
    );
}
```

**Stability commitment:** Four-accessor shape is locked. No methods beyond accessors in v0.x.

**Iterability:** `$rows` is `iterable` to permit lazy iteration in future v1.x optimisations; v0.x ships materialised arrays.

## Pagination

```php
final readonly class Pagination
{
    public function __construct(
        public int $page,            // 1-indexed
        public int $pageSize,
        public ?int $totalRows,      // null when approximateTotal=true
        public ?int $totalPages,     // null when totalRows is null
        public bool $hasPrev,
        public bool $hasNext,
    );
}
```

**Page-clamp behaviour (FR-027):** `ListingResolver` itself clamps the `?page` parameter parsed from the URL: `page ≤ 0` → `page = 1`; `page > totalPages` → `page = totalPages`. Clamp is silent (no exception); future v1.x may surface a "clamped" hint via observability event.

## Test surface (from spec §8.1)

Mandatory cases (run against both `InMemoryListingResolverTest` and `SqliteListingResolverTest` subclasses of `ListingResolverContract`):

- `resolveReturnsRowsMatchingFilters` (FR-019)
- `resolveRespectsPageSize` (FR-026)
- `resolveAppliesAccessPolicyPerRow` (FR-029)
- `resolveProducesShortPagesAfterAccessFilter` (FR-030)
- `totalRowsReflectsAccessFilteredCount` (FR-031)
- `approximateTotalSkipsFullScan` (NFR-002)
- `accessFastPathOptInSkipsPolicyLoop` (FR-032)
- `cacheTagsIncludeEntityRows` (FR-023)
- `cacheHitSecondResolveReturnsSameResult` (NFR-003)
- `unknownContextBypassesCache` (FR-035 / R-11)
- `pageClampsBelowOne` + `pageClampsAboveTotal` (FR-027)
- `implicitLangcodeFilterAppliedOnTranslatable` (FR-047)
- `cacheKeyIsDeterministic` (FR-037)

Sentinels (run in CI; do not fail build):
- `accessFastPathBenchmark` (NFR-001 — < 1 ms p95 per row)
- `cacheHitOverheadBenchmark` (NFR-003 — < 0.5 ms p95)
