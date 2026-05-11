# 015 — Listing pipeline (Views equivalent, integrate-not-build)

**Status:** Accepted (2026-05-11)
**Mission:** Stability charter ratification; future beta gate
**Spec context:** `docs/specs/drupal-comparison-matrix.md` §6.6, §1.4, §3.4

## Context

Drupal Views is the killer feature: declarative query → filter/sort/paginate → multiple display modes (page, block, feed, REST) → access-aware caching → admin-composable in the browser. For 15 years it has been the difference between Drupal and "Symfony plus an ORM."

Waaseyaa has none of this. `EntityStorageInterface::getQuery()` is the raw query surface; everything above it (filter UI, paginated rendering, cache-tag invalidation, multi-display) is app code. Listings are 60–80% of pages on community CMSs, so this is the largest mission-completeness gap in the Drupal-comparison matrix.

Three positions are plausible:

- **Build it** — multi-year mission, framework doubles in conceptual size.
- **Integrate it** — adopt or build a small query+display library, integrate via routing/entity layers.
- **Out of scope** — document the gap, lose Drupal magazine-shop migrations.

## Options considered

### A. Build a Drupal-shape Views

Full feature set: admin UI for assembling displays, plugin types for filters/sorts/displays/access/cache, exposed filters, contextual filters, relationships across entities. Multi-year. Forces framework decisions on admin UI, query AST, cache architecture all at once. Rejected for v0.x.

### B. Build minimal "listing pipeline" + admin UI deferred (CHOSEN)

Declarative `ListingDefinition` objects describe filtered/sorted/paginated entity queries. Display is template-driven (apps own rendering, per ADR 013). Cache integration via entity lifecycle events (ADR 011). Admin-composability deferred to post-v1.0. Apps register listings via service providers, same shape as routes and entities.

### C. Out of scope

Document; lose addressable market. Rejected: the mission explicitly claims to obsolete Drupal, and this is the feature Drupal users will look for first.

## Decision

A **listing pipeline** as a stable framework surface, intentionally smaller than Drupal Views in v0.x. Admin-composability is a v1.x concern.

### Contract

```php
final readonly class ListingDefinition
{
    public function __construct(
        public string $id,
        public string $entityType,
        public ?string $bundle = null,
        public array $filters = [],     // FilterDefinition[]
        public array $sorts = [],       // SortDefinition[]
        public ?int $pageSize = null,
        public array $accessOps = ['view'],
    ) {}
}
```

Apps register listings via a `HasListingsInterface` provider capability (parallel to `HasNativeCommandsInterface`):

```php
public function listings(): array
{
    return [
        new ListingDefinition(
            id: 'upcoming_events',
            entityType: 'event',
            filters: [Filter::gte('starts_at', 'now')],
            sorts: [Sort::asc('starts_at')],
            pageSize: 20,
        ),
    ];
}
```

### Resolution

`ListingResolver` (framework service) executes a `ListingDefinition`:

1. Build an `EntityQuery` from filters + sorts + paging.
2. Apply access policies on each result row (using the existing `GateInterface`).
3. Return a typed `ListingResult` with rows, pagination metadata, and cache metadata.

`ListingResult` is opaque to apps in terms of internals but exposes:

- `rows()` — iterable of entities.
- `pagination()` — current page, total pages, has-next, has-prev.
- `cacheTags()` — tags for invalidation (e.g. `entity:event`, `entity:event:42`).
- `cacheContexts()` — varying axes (e.g. `user.roles`, `url.query.page`).

### Display

**Display is app concern**, consistent with ADR 013. Apps consume a `ListingResult` in their controllers and render via Twig partials. The framework does not ship "block display" or "feed display" plugins.

Recommended pattern: a Twig component per entity type renders a row; a generic `listing.html.twig` partial renders the wrapper + pagination. The same listing can be displayed differently in different contexts by passing different components.

### Cache integration

`AfterSaveEvent` and `AfterDeleteEvent` (ADR 011) invalidate cache entries whose tags include the changed entity. The cache backend (`cache` package, Layer 0) gains tag-aware invalidation as part of this ADR's implementation. Tag/context architecture is detailed in a follow-up spec, but the contract — `cacheTags()` and `cacheContexts()` returning string arrays — is stable from v0.x.

### Query backend constraints

A listing's query is executed against the entity type's primary storage backend (ADR 010). Filtering or sorting on a field whose backend reports `supportsQuery() === false` raises `UnsupportedListingException` at definition-validation time, not at runtime. Definitions are validated at boot.

Cross-backend joins are forbidden. A listing of `dictionary_entry` cannot filter by a remote-backed field in v0.x. If demand emerges, a follow-up ADR adds cross-backend coordination.

### Exposed filters

User-controllable filters (the URL `?status=published` shape) ride on `FilterDefinition::exposed(string $param)`. The framework parses the query string, validates against the filter's type, and applies. Apps render the filter UI; framework provides the parsing.

### Admin-composability

**Deferred.** A browser UI for building listings is a v1.x concern. The current ADR provides the contract that such a UI would later sit atop.

## Consequences

- **Framework gains its largest single feature for Drupal-equivalence.** Listings — the core 60–80% of pages — become declarative.
- **Cache architecture commits to tags + contexts in v0.x.** Larger implication than the listing pipeline itself; this is the right forcing function.
- **Apps still own row rendering.** No widget/formatter plugins (ADR 013 stands).
- **Admin-UI deferral is intentional.** Building the contract now, the UI later, lets early consumers exercise the contract and shape the UI's eventual design.
- **Beta gate addition.** Charter §3.2 beta entry criteria should add: "ListingDefinition contract is stable and at least one consumer app uses it for production listings." Without that, "beta" misleads consumers.
- **The largest implementation mission in the post-charter roadmap.** Sequencing under M1 framework stability charter; concrete WPs come after charter ratification.

## References

- Matrix: `docs/specs/drupal-comparison-matrix.md` §1.4, §3.4, §6.6.
- Charter: `docs/specs/stability-charter.md` §3.2 (beta criteria — to be amended).
- Related ADRs: 010 (storage backends gate query support), 011 (lifecycle events drive cache invalidation), 013 (display stays in app land), 014 (themes can ship listing templates).
