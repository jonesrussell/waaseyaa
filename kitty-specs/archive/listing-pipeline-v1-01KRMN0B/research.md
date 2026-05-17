# Research: Listing Pipeline v1

**Phase:** 0 (research)
**Mission:** M-007 / `listing-pipeline-v1-01KRMN0B`
**Date:** 2026-05-15

## Open questions resolved

### R-01 / §11.1 — page-size cap

**Decision:** Cap at 1000. `ListingDefinition` gains a `allowUnbounded(): self` fluent builder method that flips an internal flag. The boot-time `ListingDefinitionValidator` (FR-051) rejects:
- `pageSize > 1000` unless `allowUnbounded()` was called
- `pageSize === null` unless `allowUnbounded()` was called

**Rationale:** A forgotten `pageSize` shipping a 200,000-row JSON response is a real foot-gun. Capping forces apps to make the unbounded choice explicit. Fixture/admin/CLI listings that genuinely need every row express that intent via one method call.

**Alternatives considered:**
- No cap (FR-007 trust apps): rejected for the obvious foot-gun risk.
- Hard cap (no opt-out): rejected because fixture listings and CLI export tools have legitimate unbounded needs.
- Soft-warn at 100 / cap at 1000: rejected for spec complexity; the gradient adds documentation burden without preventing the worst foot-gun.

### R-02 / §11.2 — validation timing

**Decision:** Boot-time via `PackageManifestCompiler::warm()`, post entity-type registration, pre-route dispatch.

**Rationale:** Spec §3.13 (FR-050..FR-053) requires fail-fast invalid-definition behavior — kernel refuses to boot rather than serve broken listings silently. In prod, validation runs only when `var/manifest.php` is rebuilt (a deploy-time event); per-request cost is zero. In dev, validation runs on every request (~1 ms per definition); a four-filter listing takes ~4 ms to validate, which is acceptable startup cost on a hot-reload workflow.

**Alternatives considered:**
- First-resolve validation (lazy): rejected because a never-resolved bad listing wouldn't surface until production usage triggers it. Worst-failure-mode-first.
- Two-tier (cheap checks at boot, expensive checks lazy): rejected for spec complexity; current single-tier validation is already <5 ms per listing.

### R-03 / §11.3 — approximateTotal shape

**Decision:** Per-listing constructor flag on `ListingDefinition` (bool, default `false`). Listings with `approximateTotal: true` return `null` for `Pagination::$totalRows`, skipping the full-result-set access scan.

**Rationale:** The choice is a property of the listing itself: a high-cardinality content stream (e.g. articles across all time) should always behave this way; an admin operations table where exact counts matter should not. Per-listing declaration lives in the manifest, is consistent across consumers, and is part of the cache key (different flag = different cache shape, but same flag for all consumers).

Validator rule (FR-051 extension): `approximateTotal: true` is incompatible with `pageSize === null + allowUnbounded()`. Reason: an unbounded listing returns every accessible row anyway; `null` total carries no information past "we already gave you everything".

**Alternatives considered:**
- Resolver-level option in `ExposedFilterValues`: rejected because per-request choice fragments the cache key space and makes cache behavior surprising to debug.
- Definition flag + request override: rejected for surface size; the matrix of (definition-flag, request-override) is hard to test.

### R-04 / §11.4 — cache TTL

**Decision:** Infinite default. `ListingDefinition` gains `?int $cacheTtl = null` constructor param (null = no TTL; entries live until tag-invalidation). Resolver passes the value through to `TaggedCacheInterface::setWithTags($key, $value, $tags, $cacheTtl)`.

**Rationale:** Event-driven invalidation via `AfterSaveEvent`/`AfterDeleteEvent` is the correctness mechanism (FR-038..FR-041). TTL is then purely a cost-control knob — useful for caches that grow unboundedly under low-write workloads, but not needed for correctness. Default infinite means most listings "just work" without TTL configuration; apps that want time-based eviction set a value explicitly.

**Alternatives considered:**
- 1-hour default: rejected as belt-and-suspenders that adds inconsistent freshness windows. Implies the tag system is unreliable, which the spec disagrees with.
- Framework-wide configurable default: rejected — fragmentation across deployments makes test matrices harder.

### R-05 / §11.5 — event subscription priority

**Decision:** `ListingCacheInvalidator` subscribes to `AfterSaveEvent` + `AfterDeleteEvent` at **priority=100** (high — runs first in chain).

**Rationale:** Running first means subsequent event listeners that re-resolve listings (e.g. a search-index rebuilder, an audit listener that re-reads recent posts) see a clean (invalidated) cache, not a stale one. The pathological case — a listener that re-resolves a listing within `AfterSaveEvent` — is rare but correctness depends on this ordering.

**Alternatives considered:**
- priority=-100 (run last): rejected because re-resolve-mid-save is the failure case to design against, not the common case.
- priority=0 (default): rejected because we want a deterministic ordering for tests.

### R-06 / §11.6 — strict-mode parser

**Decision:** `ExposedFilterParser` ships with a `strict()` fluent factory: `ExposedFilterParser::create()->strict()` returns a strict variant. Production uses default (silent-drop per FR-044, debug-log on coercion failure). Test environments opt in to `strict()` which throws `ListingCoercionException` on the same coercion failure.

**Rationale:** Tests want loud failures; production wants resilience. Same class, different internal flag — keeps the production code path identical to the test code path modulo the failure-mode toggle.

**Alternatives considered:**
- Always-strict, with apps catching `ListingCoercionException`: rejected because user-input failure modes happen too often and putting try/catch in every controller is ergonomically wrong.
- Two distinct classes (`StrictExposedFilterParser` + `ExposedFilterParser`): rejected for surface duplication; the fluent factory cleanly distinguishes intent.

### R-07 / §11.7 — AfterSaveEvent / AfterDeleteEvent surface patch

**Decision:** Both events gain an additive optional property:

```php
public function __construct(
    public readonly EntityInterface $entity,
    public readonly ?EntityInterface $originalEntity = null,
    public readonly ?array $affectedLangcodes = null,  // NEW
) {}
```

`SqlStorageDriver`'s translatable write path (the M-006-shipped path that writes per-langcode rows) backfills `$affectedLangcodes` with the actual array of langcodes touched in this save. Non-translatable saves leave it `null`. `ListingCacheInvalidator` reads the property; if `null`, falls back to `[$entity->activeLangcode()]` (the active langcode is always at least affected by definition).

**Rationale:** Multi-language saves (e.g. an editor saves both `en` and `mi-tle` in one transaction) need to invalidate cached listings for both langcodes. Without this property, the invalidator emits tags for the active langcode only — the other langcode's cached listings serve stale data until manual flush. The property is additive (new constructor param with default `null`), so existing consumers compile unchanged.

`AfterDeleteEvent` gets the mirror addition because a cascading translation delete affects every langcode that had translations — the invalidator needs to emit tags for all of them.

**Alternatives considered:**
- Don't patch the event surface; live with active-langcode-only invalidation in v0.x: rejected by the user during planning interrogation (see decision §11.7 in the spec). The user's chosen trade-off was the small surface addition vs. stale-cache correctness gap.
- Add the property to `AfterSaveEvent` but not `AfterDeleteEvent`: rejected for asymmetry; delete is when most invalidation work happens.

## Naming / pattern reconciliation

### R-08 — Operator enum naming

**Decision:** `Waaseyaa\Listing\Operator` as a backed enum with string values:

```php
enum Operator: string
{
    case EQ = 'eq';
    case NEQ = 'neq';
    case LT = 'lt';
    case LTE = 'lte';
    case GT = 'gt';
    case GTE = 'gte';
    case IN = 'in';
    case NOT_IN = 'not_in';
    case IS_NULL = 'is_null';
    case IS_NOT_NULL = 'is_not_null';
    case BETWEEN = 'between';
    case STARTS_WITH = 'starts_with';
    case CONTAINS = 'contains';
}
```

**Rationale:** Standard PHP convention. Backed strings allow stable string serialization for the cache-key digest (FR-005) and for `var/manifest.php` round-tripping. Case names are SCREAMING_SNAKE_CASE per PHP convention.

**No conflict:** `Waaseyaa\Validation\*` has rule classes (e.g. `Length`, `NotBlank`); listing operators are a distinct enum in a distinct namespace.

### R-09 — `Filter` / `Sort` factory class placement

**Decision:** Static factories live in sibling classes:
- `Waaseyaa\Listing\Filter` — static methods (`Filter::eq()`, `Filter::gte()`, `Filter::in()`, `Filter::isNull()`, `Filter::langcode()`, `Filter::exposed()`)
- `Waaseyaa\Listing\Sort` — static methods (`Sort::asc()`, `Sort::desc()`)

**Rationale:** Matches ADR 015's example code (`Filter::gte('starts_at', 'now')`, `Sort::asc('starts_at')`). Separates the factory concern from the value-object concern; consumers import the factory class for ergonomic construction without touching `FilterDefinition` / `SortDefinition` directly.

`Filter::exposed(FilterDefinition $base, string $param): FilterDefinition` is the only path that creates an exposed filter (FR-009); marking the constructor parameter as package-private convention. Constructing `FilterDefinition` with `$exposedParam` directly is technically possible but discouraged.

### R-10 — `HasListingsInterface` discovery

**Decision:** `PackageManifestCompiler::warm()` discovers `HasListingsInterface` implementors via `instanceof` check on each registered `ServiceProvider` (or equivalent). Returns `ListingDefinition[]` from `listings()` method. Results cached to `var/manifest.php`.

**Rationale:** Mirrors `HasNativeCommandsInterface` discovery shape exactly (which mirrors `HasMigrationsInterface` from M-002). Single discovery convention reduces cognitive load for app authors. Cached manifest means zero per-request discovery cost in prod.

**No new attribute path:** Rejected the alternative of `#[Listing]` attribute scanning on individual methods. Per-method discovery would be more flexible but inconsistent with the existing capability-interface pattern.

### R-11 — Tag-string regex enforcement

**Decision:** Tag strings must match `^[a-z][a-z0-9_:.-]*$` (lowercase, dot/dash/underscore/colon allowed after first char). Enforced in `TaggedCacheInterface::setWithTags()`; throws `InvalidCacheTagException` on mismatch. No silent normalisation (no `strtolower`, no character substitution).

**Rationale:** Codified context discipline from M-002 / M-006 — silent normalisation hides bugs; explicit rejection forces tag authors to think about format. The vocabulary is documented in `docs/conventions/cache-tags-and-contexts.md` (WP12).

Canonical tags:
- `entity:<type>` — for the entity type as a whole
- `entity:<type>:<id>` — for a specific entity
- `entity:<type>:<id>:<langcode>` — for a specific translation of a specific entity

### R-12 — No backwards-compat for `cache.setWithTags`

**Decision:** `TaggedCacheInterface` is a NEW interface; the existing `Waaseyaa\Cache\CacheInterface` is unchanged. `MemoryBackend` implements both (additive). Apps using only `CacheInterface` see no change. Apps wanting tag-aware operations depend on the new interface explicitly.

**Rationale:** Standard interface-segregation. No magic; consumers opt into the new surface by importing the new interface.

## Cross-mission impact summary

| Mission | Impact | Lands in |
|---|---|---|
| M-001 (entity-storage-v2) | Consumes `EntityQuery::supportsQuery()` and `UnsupportedQueryException`. No new burden. | — |
| M-006 (entity-storage-translations-v1) | Consumes `TranslatableInterface` and `SaveContext::langcode`. Satisfies C-002 obligation. `SqlStorageDriver` patched in WP08 to backfill `AfterSaveEvent.affectedLangcodes` — this lives in `entity-storage` package. | M-007 WP08 |
| M-004 (entity-storage-translatable-revisions) | Fully unblocks when M-007 ships. WP07 of M-004 (per-langcode listing filters, langcode cache tags) is the direct downstream consumer. | M-007 mission close |
| M-002 (migration-platform-v1) | None. Migrations are write paths; listings are read paths. | — |
| M-005 (waaseyaa/migrate-source-wordpress) | None. | — |
| M-003 (config-management-v1) | None. Config entities are not listing subjects. | — |

## Stability-charter amendments authored (WP12)

Three amendments land at mission close:

1. **§3.2 criterion 10** — new beta-entry criterion: *"`ListingDefinition` contract is stable and at least one consumer app uses it for production listings."* Wording from ADR 015 §Consequences, verbatim.
2. **§5.X (number assigned at amendment time, in line with §5.8 for migration)** — listing package stable surface. Lists `ListingDefinition`, `FilterDefinition`, `SortDefinition`, `Operator`, `Filter`, `Sort`, `SortDirection`, `Pagination`, `ListingResult`, `ListingResolver`, `ListingDefinitionRegistry`, `HasListingsInterface`, `ExposedFilterValues`, `ExposedFilterParser`, `UnsupportedListingException`, `UnknownListingException`. Marks `ListingCacheKeyBuilder`, `ListingCacheInvalidator`, `ListingDefinitionValidator`, `ExposedFilterCoercer`, `ListingCoercionException` as INTERNAL.
3. **§5.Y (number assigned at amendment time)** — cache tag/context stable surface. Lists `TaggedCacheInterface`, `ContextResolver`, `ContextRegistry`, `ContextNames` canonical constants, tag-string regex, canonical tag vocabulary.

## Open data-model questions deferred to data-model.md / contracts/

- Exact constructor signature for `ListingDefinition` (parameter order, default values).
- `FilterDefinition::$value` typing — `mixed` or per-operator typed unions via attribute?
- `Pagination` constructor signature for the `approximateTotal === true` path (does `$totalPages` also become `null`?).
- `ExposedFilterValues` getter shape — `get(string $param): mixed`, or per-typed methods?
- `ContextResolver::resolve()` return — string only, or `string|null` for unknown contexts?

These are not mission-shape decisions; they are interface-detail decisions that land in `data-model.md` and the `contracts/` files in this phase.
