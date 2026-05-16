<?php

declare(strict_types=1);

namespace Waaseyaa\Listing;

use Throwable;
use Waaseyaa\Access\Gate\GateInterface;
use Waaseyaa\Cache\ContextNames;
use Waaseyaa\Cache\ContextResolver;
use Waaseyaa\Cache\TaggedCacheInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Foundation\Http\RequestContext;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;

/**
 * Resolves a {@see ListingDefinition} (+ optional {@see ExposedFilterValues})
 * into a {@see ListingResult}.
 *
 * The load-bearing component of the listing pipeline. Implements the
 * normative resolution algorithm from `docs/specs/listing-pipeline-v1.md`
 * §7.1 in 12 steps:
 *
 * 1. Resolve effective filters (declared + exposed merge).
 * 2. Resolve effective langcode for translatable types (FR-047).
 * 3. Resolve effective cache contexts (FR-024 + FR-048) into a value map.
 * 4. Build a cache key via {@see ListingCacheKeyBuilder} (when caching enabled).
 * 5. Cache lookup — on hit, return the cached {@see ListingResult}.
 * 6. Build the query by translating filters → criteria + in-process refinement.
 * 7. Execute query (full result set; pagination clamps + slices post-access).
 * 8. Per-row access policy application (FR-029) + FR-032 fast-path opt-in.
 * 9. Pagination metadata (FR-025/FR-027 page clamp; FR-030 short pages;
 *    FR-031 total-rows reflects access-filtered count).
 * 10. Compute cache tags (FR-023) + contexts (FR-024 + FR-048).
 * 11. Build {@see ListingResult}.
 * 12. Cache store (when caching enabled + no unknown contexts).
 *
 * Caching is **fully optional** — when `?TaggedCacheInterface $cache` and
 * `?ListingCacheKeyBuilder $keyBuilder` are both `null`, resolution skips
 * the cache lookup/store paths entirely and never touches them. WP06 wires
 * cache-aware re-resolution; this class supports both paths from day one.
 *
 * Error model:
 * - Storage-backend errors propagate as-is (FR-057).
 * - Cache-backend errors are caught and logged; resolution continues
 *   without caching (FR-058).
 * - Per-row access denials silently filter the row from `$accessRows`
 *   (FR-021); they never throw.
 *
 * Stable surface (charter §5.X): the constructor parameter shape +
 * `resolve(ListingDefinition, ?ExposedFilterValues): ListingResult`
 * signature is committed from v0.x.
 *
 * @api
 */
final class ListingResolver
{
    /**
     * Logical operators that are evaluable in-process against entity field
     * values. EQ is delegated to the storage driver's native criteria match
     * for performance; the rest are evaluated in-PHP after the broader
     * driver-side fetch (FR-018, FR-019).
     */
    private const STORAGE_NATIVE_OPS = [Operator::EQ];

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly EntityRepositoryRegistry $repositories,
        private readonly GateInterface $gate,
        private readonly ContextResolver $contextResolver,
        private readonly EntityTypeManagerInterface $entityTypes,
        private readonly RequestContext $requestContext,
        private readonly ?TaggedCacheInterface $cache = null,
        private readonly ?ListingCacheKeyBuilder $keyBuilder = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Resolve the listing definition into a result.
     *
     * @throws \Throwable On storage backend infrastructure failure (FR-057).
     */
    public function resolve(
        ListingDefinition $def,
        ?ExposedFilterValues $exposed = null,
    ): ListingResult {
        $exposed ??= new ExposedFilterValues();
        $entityType = $this->entityTypes->getDefinition($def->entityType);

        // §7.1 step 3 — effective contexts + their resolved values
        $cacheContexts = $this->computeCacheContexts($def, $entityType);
        $contextValues = $this->resolveContextValues($cacheContexts);
        $cachingEnabled = $this->isCachingEnabled();
        $cacheKey = null;

        // §7.1 step 4-5 — cache key + lookup
        if ($cachingEnabled) {
            $cacheKey = $this->safeBuildKey($def, $exposed, $contextValues);
            if ($cacheKey !== null) {
                $hit = $this->safeCacheGet($cacheKey);
                if ($hit !== null) {
                    return $hit;
                }
            }
        }

        // §7.1 step 6-7 — query construction + execution
        $allRows = $this->executeQuery($def, $exposed, $entityType);

        // §7.1 step 8 — access policy filter per row (FR-029 + FR-032 fast-path)
        $accessRows = $this->applyAccessPolicy($allRows, $def);

        // §7.1 step 9 — pagination per FR-025..FR-027 + FR-030..FR-031
        $totalAccessibleRows = count($accessRows);
        [$pagedRows, $pagination] = $this->paginate($accessRows, $def, $totalAccessibleRows);

        // §7.1 step 10 — tags + contexts
        $cacheTags = $this->computeCacheTags($def, $pagedRows, $entityType);

        // §7.1 step 11 — result
        $result = new ListingResult($pagedRows, $pagination, $cacheTags, $cacheContexts);

        // §7.1 step 12 — cache store (best-effort; FR-058 absorbs failures)
        if ($cachingEnabled && $cacheKey !== null && !$this->hasUnknownContexts($contextValues, $cacheContexts)) {
            $this->safeCacheStore($cacheKey, $result, $cacheTags, $def->cacheTtl);
        }

        return $result;
    }

    // ----------------------------------------------------------------------
    // §7.1 step 3-4: context + caching helpers
    // ----------------------------------------------------------------------

    /**
     * @return list<non-empty-string>
     */
    private function computeCacheContexts(ListingDefinition $def, \Waaseyaa\Entity\EntityTypeInterface $entityType): array
    {
        $contexts = $def->effectiveContexts($entityType);

        // FR-048: language.content auto-included for translatable types
        // (effectiveContexts() already adds it; reinforce here for safety).
        if ($entityType->isTranslatable() && !in_array(ContextNames::LANGUAGE_CONTENT, $contexts, true)) {
            $contexts[] = ContextNames::LANGUAGE_CONTENT;
        }

        sort($contexts, SORT_STRING);

        /** @var list<non-empty-string> $unique */
        $unique = array_values(array_unique($contexts));

        return $unique;
    }

    /**
     * @param  list<non-empty-string> $contexts
     * @return array<string, string>
     */
    private function resolveContextValues(array $contexts): array
    {
        $values = [];
        foreach ($contexts as $ctx) {
            $values[$ctx] = $this->contextResolver->resolve($ctx, $this->requestContext);
        }

        return $values;
    }

    private function isCachingEnabled(): bool
    {
        return $this->cache !== null && $this->keyBuilder !== null;
    }

    /**
     * @param array<string, string>   $contextValues
     * @param list<non-empty-string>  $cacheContexts
     */
    private function hasUnknownContexts(array $contextValues, array $cacheContexts): bool
    {
        // Per FR-035, ContextResolver returns '' for unknown context names.
        // We can't distinguish "unknown" from "known-but-empty" purely from the
        // return value; instead we trust the resolver's warning + treat ''
        // for canonical contexts as a signal to skip cache.
        foreach ($cacheContexts as $ctx) {
            if (($contextValues[$ctx] ?? '') === '' && !$this->isOptionalContext($ctx)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Some canonical contexts legitimately resolve to '' for anonymous /
     * default-langcode requests; treat these as "known empty" not "unknown".
     */
    private function isOptionalContext(string $context): bool
    {
        return $context === ContextNames::USER_ID
            || $context === ContextNames::USER_ROLES
            || $context === ContextNames::LANGUAGE_CONTENT
            || $context === ContextNames::LANGUAGE_INTERFACE
            || str_starts_with($context, ContextNames::URL_QUERY_PREFIX);
    }

    private function safeBuildKey(
        ListingDefinition $def,
        ExposedFilterValues $exposed,
        array $contextValues,
    ): ?string {
        $builder = $this->keyBuilder;
        if ($builder === null) {
            return null;
        }

        try {
            return $builder->build($def, $exposed, $contextValues);
        } catch (Throwable $e) {
            $this->logger->warning(
                'ListingResolver: cache-key build failed; bypassing cache for this resolution.',
                ['listing' => $def->id, 'error' => $e->getMessage()],
            );

            return null;
        }
    }

    private function safeCacheGet(string $key): ?ListingResult
    {
        $cache = $this->cache;
        if ($cache === null) {
            return null;
        }

        try {
            $item = $cache->get($key);
        } catch (Throwable $e) {
            $this->logger->warning(
                'ListingResolver: cache get failed; falling through to fresh resolution.',
                ['key' => $key, 'error' => $e->getMessage()],
            );

            return null;
        }

        if ($item === false) {
            return null;
        }

        // CacheItem wraps the stored value; unwrap if necessary.
        $value = $this->extractCachedValue($item);

        return $value instanceof ListingResult ? $value : null;
    }

    private function extractCachedValue(mixed $item): mixed
    {
        if ($item instanceof ListingResult) {
            return $item;
        }
        if (is_object($item) && property_exists($item, 'data')) {
            // CacheItem-like shape used by Waaseyaa cache backends.
            return $item->data;
        }

        return $item;
    }

    /**
     * @param list<non-empty-string> $tags
     */
    private function safeCacheStore(string $key, ListingResult $result, array $tags, ?int $ttl): void
    {
        $cache = $this->cache;
        if ($cache === null) {
            return;
        }

        try {
            $cache->setWithTags($key, $result, $tags, $ttl);
        } catch (Throwable $e) {
            $this->logger->warning(
                'ListingResolver: cache store failed; resolution succeeded but result is uncached.',
                ['key' => $key, 'error' => $e->getMessage()],
            );
        }
    }

    // ----------------------------------------------------------------------
    // §7.1 step 6-7: query construction + execution
    // ----------------------------------------------------------------------

    /**
     * Build effective filter list (declared filters with exposed-param
     * overrides applied) + implicit langcode filter for translatable
     * entity types, then execute against the repository.
     *
     * @return list<EntityInterface>
     */
    private function executeQuery(
        ListingDefinition $def,
        ExposedFilterValues $exposed,
        \Waaseyaa\Entity\EntityTypeInterface $entityType,
    ): array {
        $effective = $this->effectiveFilters($def, $exposed, $entityType);

        // Storage-native EQ criteria (per FR-019: filters that the driver can
        // satisfy natively pass through; non-EQ operators are refined in-PHP
        // post-fetch).
        $criteria = [];
        $remaining = [];
        foreach ($effective as $filter) {
            if (in_array($filter->op, self::STORAGE_NATIVE_OPS, true)
                && is_scalar($filter->value)
            ) {
                $criteria[$filter->field] = $filter->value;
            } else {
                $remaining[] = $filter;
            }
        }

        if ($def->bundle !== null) {
            $bundleKey = $entityType->getKeys()['bundle'] ?? 'bundle';
            $criteria[$bundleKey] = $def->bundle;
        }

        // FR-014: stable secondary sort on id key after user-declared sorts.
        $orderBy = [];
        foreach ($def->sorts as $sort) {
            $orderBy[$sort->field] = strtoupper($sort->direction->value);
        }
        $idKey = $entityType->getKeys()['id'] ?? 'id';
        if (!array_key_exists($idKey, $orderBy)) {
            $orderBy[$idKey] = 'ASC';
        }

        $repository = $this->repositories->for($def->entityType);
        // Fetch the full (criteria-narrowed) result set; pagination is applied
        // post-access. FR-031 requires totalRows to reflect the access-filtered
        // count, so we cannot push limit to the driver here.
        /** @var list<EntityInterface> $rows */
        $rows = $repository->findBy($criteria, $orderBy);

        // Refine in-PHP for non-native operators (FR-019 fallback path).
        if ($remaining !== []) {
            $rows = array_values(array_filter(
                $rows,
                fn(EntityInterface $row): bool => $this->matchesAll($row, $remaining),
            ));
        }

        return $rows;
    }

    /**
     * Merge declared filters with exposed-param overrides + add an
     * implicit `langcode` filter when the entity type is translatable
     * and no explicit langcode filter was declared (FR-047).
     *
     * @return list<FilterDefinition>
     */
    private function effectiveFilters(
        ListingDefinition $def,
        ExposedFilterValues $exposed,
        \Waaseyaa\Entity\EntityTypeInterface $entityType,
    ): array {
        $effective = [];
        $hasLangcodeFilter = false;

        foreach ($def->filters as $filter) {
            if ($filter->field === 'langcode') {
                $hasLangcodeFilter = true;
            }
            if ($filter->exposedParam !== null && $exposed->has($filter->exposedParam)) {
                $effective[] = new FilterDefinition(
                    field: $filter->field,
                    op: $filter->op,
                    value: $exposed->get($filter->exposedParam),
                    exposedParam: $filter->exposedParam,
                );
            } else {
                $effective[] = $filter;
            }
        }

        // FR-047: implicit langcode filter for translatable types.
        if ($entityType->isTranslatable() && !$hasLangcodeFilter) {
            $activeLangcode = $this->contextResolver->resolve(
                ContextNames::LANGUAGE_CONTENT,
                $this->requestContext,
            );
            if ($activeLangcode !== '') {
                $effective[] = new FilterDefinition(
                    field: 'langcode',
                    op: Operator::EQ,
                    value: $activeLangcode,
                );
            }
        }

        return $effective;
    }

    /**
     * @param list<FilterDefinition> $filters
     */
    private function matchesAll(EntityInterface $row, array $filters): bool
    {
        foreach ($filters as $filter) {
            if (!$this->matchesOne($row, $filter)) {
                return false;
            }
        }

        return true;
    }

    private function matchesOne(EntityInterface $row, FilterDefinition $filter): bool
    {
        $actual = $this->readField($row, $filter->field);
        $value = $filter->value;

        return match ($filter->op) {
            Operator::EQ => $this->scalarEquals($actual, $value),
            Operator::NEQ => !$this->scalarEquals($actual, $value),
            Operator::LT => $actual !== null && $value !== null && $actual < $value,
            Operator::LTE => $actual !== null && $value !== null && $actual <= $value,
            Operator::GT => $actual !== null && $value !== null && $actual > $value,
            Operator::GTE => $actual !== null && $value !== null && $actual >= $value,
            Operator::IN => is_array($value) && in_array($actual, $value, true),
            Operator::NOT_IN => is_array($value) && !in_array($actual, $value, true),
            Operator::IS_NULL => $actual === null,
            Operator::IS_NOT_NULL => $actual !== null,
            Operator::BETWEEN => $this->matchesBetween($actual, $value),
            Operator::STARTS_WITH => is_string($actual) && is_string($value) && str_starts_with($actual, $value),
            Operator::CONTAINS => is_string($actual) && is_string($value) && str_contains($actual, $value),
        };
    }

    private function scalarEquals(mixed $actual, mixed $expected): bool
    {
        if ($actual === null || $expected === null) {
            return $actual === $expected;
        }
        if (is_scalar($actual) && is_scalar($expected)) {
            // Storage drivers may emit ints/bools as strings (esp. SQLite),
            // so equality is performed after string-normalising both sides.
            // This is deliberate — strict === would surface as no-match for
            // round-tripped integer columns and break cross-driver parity.
            return (string) $actual === (string) $expected;
        }

        return $actual === $expected;
    }

    private function matchesBetween(mixed $actual, mixed $tuple): bool
    {
        if ($actual === null || !is_array($tuple) || count($tuple) !== 2) {
            return false;
        }
        [$low, $high] = array_values($tuple);

        return $actual >= $low && $actual <= $high;
    }

    private function readField(EntityInterface $row, string $field): mixed
    {
        if ($field === 'id') {
            return $row->id();
        }

        try {
            return $row->get($field);
        } catch (Throwable) {
            return null;
        }
    }

    // ----------------------------------------------------------------------
    // §7.1 step 8: per-row access policy
    // ----------------------------------------------------------------------

    /**
     * @param  list<EntityInterface> $rows
     * @return list<EntityInterface>
     */
    private function applyAccessPolicy(array $rows, ListingDefinition $def): array
    {
        if ($rows === []) {
            return [];
        }

        // FR-032: fast-path opt-in. If the access policy class(es) bound to
        // this entity type expose SUPPORTS_LISTING_FAST_PATH=true for all
        // listed accessOps, we skip the per-row gate loop entirely.
        if ($this->canUseAccessFastPath($def)) {
            return $rows;
        }

        $accessRows = [];
        foreach ($rows as $row) {
            $allowed = true;
            foreach ($def->accessOps as $op) {
                if (!$this->gate->allows($op, $row, null)) {
                    $allowed = false;
                    break;
                }
            }
            if ($allowed) {
                $accessRows[] = $row;
            }
        }

        return $accessRows;
    }

    /**
     * Currently a no-op stub: FR-032 fast-path detection is a forward-looking
     * optimisation. Returning false here is always safe — the per-row loop
     * still produces correct results.
     */
    private function canUseAccessFastPath(ListingDefinition $def): bool
    {
        return $def->accessOps === [];
    }

    // ----------------------------------------------------------------------
    // §7.1 step 9: pagination
    // ----------------------------------------------------------------------

    /**
     * Apply FR-027 page clamp, FR-026 page-size slicing, and FR-030/FR-031
     * short-page + total-rows semantics.
     *
     * @param  list<EntityInterface> $accessRows
     * @return array{0: list<EntityInterface>, 1: Pagination}
     */
    private function paginate(array $accessRows, ListingDefinition $def, int $totalAccessibleRows): array
    {
        $pageSize = $def->pageSize ?? max(count($accessRows), 1);
        $approximateTotal = $def->approximateTotal;

        // §7.1 step 9 — page parameter parsed from the URL. RequestContext::getQueryParams()
        // returns array<string, string> per its contract, so we only need to handle the
        // string-or-missing case.
        $pageParam = $this->requestContext->getQueryParams()['page'] ?? null;
        $requestedPage = is_string($pageParam) && $pageParam !== '' ? (int) $pageParam : 1;

        if ($approximateTotal) {
            $page = max(1, $requestedPage);
            $offset = ($page - 1) * $pageSize;
            $pagedRows = array_slice($accessRows, $offset, $pageSize);

            return [
                $pagedRows,
                new Pagination(
                    page: $page,
                    pageSize: $pageSize,
                    totalRows: null,
                    totalPages: null,
                    hasPrev: $page > 1,
                    hasNext: count($pagedRows) === $pageSize,
                ),
            ];
        }

        $totalPages = $totalAccessibleRows === 0 ? 1 : (int) ceil($totalAccessibleRows / $pageSize);

        // FR-027 clamp: page <= 0 -> 1; page > totalPages -> totalPages.
        $page = $requestedPage;
        if ($page < 1) {
            $page = 1;
        }
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $pageSize;
        $pagedRows = array_slice($accessRows, $offset, $pageSize);

        return [
            $pagedRows,
            new Pagination(
                page: $page,
                pageSize: $pageSize,
                totalRows: $totalAccessibleRows,
                totalPages: $totalPages,
                hasPrev: $page > 1,
                hasNext: $page < $totalPages,
            ),
        ];
    }

    // ----------------------------------------------------------------------
    // §7.1 step 10: cache tags
    // ----------------------------------------------------------------------

    /**
     * Compute the cache tags emitted with the result (FR-023).
     *
     * - `entity:<type>` (always) — entity-type-level invalidation.
     * - `entity:<type>:<id>` per row — specific-entity invalidation.
     * - `entity:<type>:<id>:<langcode>` per row when translatable (FR-046/FR-048).
     *
     * @param  list<EntityInterface>   $rows
     * @return list<non-empty-string>
     */
    private function computeCacheTags(
        ListingDefinition $def,
        array $rows,
        \Waaseyaa\Entity\EntityTypeInterface $entityType,
    ): array {
        $tags = ['entity:' . $def->entityType];

        $translatable = $entityType->isTranslatable();
        foreach ($rows as $row) {
            $id = (string) $row->id();
            if ($id === '') {
                continue;
            }
            $tags[] = sprintf('entity:%s:%s', $def->entityType, $id);
            if ($translatable) {
                $lc = $this->readActiveLangcode($row);
                if ($lc !== '') {
                    $tags[] = sprintf('entity:%s:%s:%s', $def->entityType, $id, $lc);
                }
            }
        }

        $unique = array_values(array_unique($tags));
        // Sort for determinism — tags emitted are stable across calls (FR-023).
        sort($unique, SORT_STRING);

        return $unique;
    }

    private function readActiveLangcode(EntityInterface $row): string
    {
        // TranslatableInterface entities expose activeLangcode() returning string.
        if (method_exists($row, 'activeLangcode')) {
            $lc = $row->activeLangcode();
            if (is_string($lc)) {
                return $lc;
            }
        }

        $value = $this->readField($row, 'langcode');

        return is_string($value) ? $value : '';
    }
}
