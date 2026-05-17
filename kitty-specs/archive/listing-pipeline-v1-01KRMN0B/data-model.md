# Data Model: Listing Pipeline v1

**Phase:** 1 (design)
**Mission:** M-007 / `listing-pipeline-v1-01KRMN0B`
**Date:** 2026-05-15

All types live under `declare(strict_types=1)` per project standard. `final readonly class` is the default for value objects; `final class` for services; intersection types where contracts compose.

## Listing package — value objects

### `Waaseyaa\Listing\ListingDefinition`

```php
final readonly class ListingDefinition
{
    /**
     * @param non-empty-string $id
     * @param non-empty-string $entityType
     * @param ?non-empty-string $bundle
     * @param list<FilterDefinition> $filters
     * @param list<SortDefinition> $sorts
     * @param ?positive-int $pageSize
     * @param non-empty-list<non-empty-string> $accessOps
     * @param ?positive-int $cacheTtl
     */
    public function __construct(
        public string $id,
        public string $entityType,
        public ?string $bundle = null,
        public array $filters = [],
        public array $sorts = [],
        public ?int $pageSize = 20,
        public array $accessOps = ['view'],
        public bool $approximateTotal = false,
        public ?int $cacheTtl = null,
        private bool $unbounded = false,
    ) {
        $this->validateShallow();
    }

    /** Fluent builder: opt out of page-size cap (FR-051 / R-01). */
    public function allowUnbounded(): self
    {
        return new self(
            $this->id, $this->entityType, $this->bundle,
            $this->filters, $this->sorts, $this->pageSize,
            $this->accessOps, $this->approximateTotal, $this->cacheTtl,
            unbounded: true,
        );
    }

    public function isUnbounded(): bool { return $this->unbounded; }

    /** Effective cache contexts: declared + implicit per FR-024. */
    public function effectiveContexts(EntityTypeInterface $entityType): array { /* … */ }

    /** Deterministic hash for cache-key (FR-005, FR-037). SHA-256 over canonical JSON. */
    public function cacheKeyHash(): string { /* … */ }

    private function validateShallow(): void
    {
        // id, entityType regex match; non-empty constraints; accessOps non-empty
        // Heavy validation (supportsQuery, langcode-on-translatable) is deferred to
        // ListingDefinitionValidator::validate() at boot.
    }
}
```

**Constructor invariants enforced at construction:**
- `$id` matches `/^[a-z][a-z0-9_]*$/`
- `$entityType` non-empty string
- `$accessOps` non-empty
- `$pageSize > 0` if not null

**Cross-field invariants enforced at boot (ListingDefinitionValidator):**
- `pageSize > 1000` AND `!isUnbounded()` → `UnsupportedListingException`
- `pageSize === null` AND `!isUnbounded()` → `UnsupportedListingException`
- `approximateTotal === true` AND `pageSize === null` AND `isUnbounded()` → `UnsupportedListingException` (combination has no useful semantics)
- All filter/sort fields exist on the entity type
- All filter/sort fields' backends support query
- Langcode filters only on translatable entity types
- Operator-type compatibility with each filter's field

### `Waaseyaa\Listing\FilterDefinition`

```php
final readonly class FilterDefinition
{
    /**
     * @param non-empty-string $field
     * @param ?non-empty-string $exposedParam
     */
    public function __construct(
        public string $field,
        public Operator $op,
        public mixed $value,
        public ?string $exposedParam = null,
    ) {
        $this->validateOperatorValueShape();
    }

    /** Returns a new instance with $exposedParam set. */
    public function withExposed(string $param): self { /* … */ }

    private function validateOperatorValueShape(): void
    {
        // FR-010: IN/NOT_IN empty array → InvalidArgumentException
        // FR-011: operator-to-value-type per matrix:
        //   EQ/NEQ/LT/LTE/GT/GTE/STARTS_WITH/CONTAINS: scalar
        //   IN/NOT_IN: non-empty list
        //   BETWEEN: tuple [low, high]
        //   IS_NULL/IS_NOT_NULL: null
    }
}
```

### `Waaseyaa\Listing\SortDefinition`

```php
final readonly class SortDefinition
{
    public function __construct(
        public string $field,
        public SortDirection $direction = SortDirection::ASC,
    ) {}
}
```

### `Waaseyaa\Listing\Operator` (enum)

```php
enum Operator: string
{
    case EQ          = 'eq';
    case NEQ         = 'neq';
    case LT          = 'lt';
    case LTE         = 'lte';
    case GT          = 'gt';
    case GTE         = 'gte';
    case IN          = 'in';
    case NOT_IN      = 'not_in';
    case IS_NULL     = 'is_null';
    case IS_NOT_NULL = 'is_not_null';
    case BETWEEN     = 'between';
    case STARTS_WITH = 'starts_with';   // case-insensitive LIKE; '%' '_' escaped
    case CONTAINS    = 'contains';       // case-insensitive LIKE; '%' '_' escaped
}
```

### `Waaseyaa\Listing\SortDirection` (enum)

```php
enum SortDirection: string
{
    case ASC  = 'asc';
    case DESC = 'desc';
}
```

### `Waaseyaa\Listing\Filter` (static factory)

```php
final class Filter
{
    public static function eq(string $field, mixed $value): FilterDefinition;
    public static function neq(string $field, mixed $value): FilterDefinition;
    public static function lt(string $field, mixed $value): FilterDefinition;
    public static function lte(string $field, mixed $value): FilterDefinition;
    public static function gt(string $field, mixed $value): FilterDefinition;
    public static function gte(string $field, mixed $value): FilterDefinition;
    public static function in(string $field, array $values): FilterDefinition;
    public static function notIn(string $field, array $values): FilterDefinition;
    public static function isNull(string $field): FilterDefinition;
    public static function isNotNull(string $field): FilterDefinition;
    public static function between(string $field, mixed $low, mixed $high): FilterDefinition;
    public static function startsWith(string $field, string $value): FilterDefinition;
    public static function contains(string $field, string $value): FilterDefinition;
    public static function langcode(string $code): FilterDefinition;  // FR-046
    public static function exposed(FilterDefinition $base, string $param): FilterDefinition;  // FR-009
}
```

### `Waaseyaa\Listing\Sort` (static factory)

```php
final class Sort
{
    public static function asc(string $field): SortDefinition;
    public static function desc(string $field): SortDefinition;
}
```

### `Waaseyaa\Listing\Pagination`

```php
final readonly class Pagination
{
    /**
     * @param positive-int $page  1-indexed
     * @param positive-int $pageSize
     * @param ?int $totalRows  null when approximateTotal=true; else 0..N
     * @param ?positive-int $totalPages  null when totalRows is null
     */
    public function __construct(
        public int $page,
        public int $pageSize,
        public ?int $totalRows,
        public ?int $totalPages,
        public bool $hasPrev,
        public bool $hasNext,
    ) {}
}
```

### `Waaseyaa\Listing\ListingResult`

```php
final readonly class ListingResult
{
    /**
     * @param iterable<EntityInterface> $rows
     * @param list<non-empty-string> $cacheTags
     * @param list<non-empty-string> $cacheContexts
     */
    public function __construct(
        public iterable $rows,
        public Pagination $pagination,
        public array $cacheTags,
        public array $cacheContexts,
    ) {}
}
```

### `Waaseyaa\Listing\ExposedFilterValues`

```php
final readonly class ExposedFilterValues
{
    /** @param array<non-empty-string, mixed> $values */
    public function __construct(private array $values = []) {}

    public function get(string $param): mixed { return $this->values[$param] ?? null; }
    public function has(string $param): bool { return array_key_exists($param, $this->values); }
    public function all(): array { return $this->values; }

    /** Deterministic hash for cache-key (FR-037). */
    public function cacheKeyHash(): string { /* canonical JSON → SHA-256 → 16 hex */ }
}
```

## Listing package — services

### `Waaseyaa\Listing\ListingResolver`

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
    ) {}

    /** FR-018 / §7.1 algorithm. */
    public function resolve(
        ListingDefinition $def,
        ?ExposedFilterValues $exposed = null,
    ): ListingResult { /* … */ }
}
```

### `Waaseyaa\Listing\ListingDefinitionRegistry`

```php
final class ListingDefinitionRegistry
{
    /** @param array<non-empty-string, ListingDefinition> $byId */
    public function __construct(private readonly array $byId) {}

    /** @throws UnknownListingException */
    public function get(string $id): ListingDefinition
    {
        return $this->byId[$id] ?? throw new UnknownListingException($id);
    }

    public function has(string $id): bool { return isset($this->byId[$id]); }

    /** @return array<non-empty-string, ListingDefinition> */
    public function all(): array { return $this->byId; }
}
```

### `Waaseyaa\Listing\ListingDefinitionValidator`

```php
final class ListingDefinitionValidator
{
    public function __construct(
        private readonly EntityTypeManager $entityTypes,
    ) {}

    /**
     * @throws UnsupportedListingException on first failure (fail-fast)
     */
    public function validate(ListingDefinitionRegistry $registry): void { /* … */ }
}
```

### `Waaseyaa\Listing\ListingCacheKeyBuilder`

```php
final class ListingCacheKeyBuilder
{
    /** FR-037 format: listing:<def-hash>:<exposed-hash>:<ctx-hash>. */
    public function build(
        ListingDefinition $def,
        ExposedFilterValues $exposed,
        array $contextValues,
    ): string { /* … */ }
}
```

### `Waaseyaa\Listing\ListingCacheInvalidator`

```php
final class ListingCacheInvalidator
{
    public function __construct(
        private readonly TaggedCacheInterface $cache,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /** Subscribed at priority=100 (R-05). */
    public function onAfterSave(AfterSaveEvent $event): void { /* FR-039 */ }
    public function onAfterDelete(AfterDeleteEvent $event): void { /* FR-039 mirror */ }
}
```

### `Waaseyaa\Listing\ExposedFilterParser`

```php
final class ExposedFilterParser
{
    public function __construct(
        private readonly ExposedFilterCoercer $coercer,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $strict = false,
    ) {}

    public static function create(): self { return new self(new ExposedFilterCoercer()); }

    public function strict(): self
    {
        return new self($this->coercer, $this->logger, strict: true);
    }

    /** FR-042..FR-045. */
    public function parse(array $queryParams, ListingDefinition $def): ExposedFilterValues { /* … */ }
}
```

### `Waaseyaa\Listing\ExposedFilterCoercer`

```php
final class ExposedFilterCoercer
{
    /**
     * @throws ListingCoercionException on failure (caller decides whether to swallow per FR-044)
     */
    public function coerce(string $raw, Operator $op, string $typedDataType): mixed { /* … */ }
}
```

### `Waaseyaa\Listing\HasListingsInterface`

```php
interface HasListingsInterface
{
    /** @return list<ListingDefinition> */
    public function listings(): array;
}
```

## Listing package — exceptions

```php
final class UnsupportedListingException extends \RuntimeException
{
    public function __construct(
        public readonly string $listingId,
        public readonly ?string $fieldName,
        public readonly string $reason,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(\sprintf('Listing %s rejected: %s', $listingId, $reason), 0, $previous);
    }
}

final class UnknownListingException extends \RuntimeException
{
    public function __construct(public readonly string $listingId)
    {
        parent::__construct(\sprintf('Unknown listing id: %s', $listingId));
    }
}

/** Internal — production caller must catch and silent-drop per FR-044. */
final class ListingCoercionException extends \RuntimeException {}
```

## Cache package — extensions

### `Waaseyaa\Cache\TaggedCacheInterface`

```php
interface TaggedCacheInterface extends CacheInterface
{
    /**
     * @param non-empty-string $key
     * @param list<non-empty-string> $tags
     * @throws InvalidCacheTagException on tag that doesn't match [a-z][a-z0-9_:.-]*
     */
    public function setWithTags(string $key, mixed $value, array $tags, ?int $ttl = null): void;

    /** @return int evicted-entry count (best-effort). */
    public function invalidateByTag(string $tag): int;

    /** Introspection for tests. @return list<non-empty-string> */
    public function getTagsFor(string $key): array;
}
```

### `Waaseyaa\Cache\ContextRegistry`

```php
final class ContextRegistry
{
    /** @var array<non-empty-string, true> */
    private array $known;

    public function __construct() { /* seed canonical names */ }

    /** @throws InvalidArgumentException on invalid name. */
    public function register(string $name): void { /* … */ }

    public function has(string $name): bool { return isset($this->known[$name]); }
}
```

### `Waaseyaa\Cache\ContextResolver`

```php
final class ContextResolver
{
    public function __construct(
        private readonly ContextRegistry $registry,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /** FR-036. Returns deterministic canonical string for the current request. */
    public function resolve(string $context, RequestContext $request): string { /* … */ }
}
```

### `Waaseyaa\Cache\ContextNames` (constants)

```php
final class ContextNames
{
    public const USER_ROLES        = 'user.roles';
    public const USER_ID           = 'user.id';
    public const URL_QUERY_PREFIX  = 'url.query.';   // url.query.<param>
    public const LANGUAGE_CONTENT  = 'language.content';
    public const LANGUAGE_INTERFACE = 'language.interface';
}
```

### `Waaseyaa\Cache\Exception\InvalidCacheTagException`

```php
final class InvalidCacheTagException extends \InvalidArgumentException {}
```

## Foundation package — event surface patch (WP08)

### `Waaseyaa\Foundation\Event\AfterSaveEvent` (additive)

```php
final readonly class AfterSaveEvent extends EntityEvent
{
    public function __construct(
        public EntityInterface $entity,
        public ?EntityInterface $originalEntity = null,
        public ?array $affectedLangcodes = null,  // NEW — list<non-empty-string>|null
    ) {
        parent::__construct(/* … */);
    }
}
```

### `Waaseyaa\Foundation\Event\AfterDeleteEvent` (additive)

```php
final readonly class AfterDeleteEvent extends EntityEvent
{
    public function __construct(
        public EntityInterface $entity,
        public ?array $affectedLangcodes = null,  // NEW — list<non-empty-string>|null
    ) {
        parent::__construct(/* … */);
    }
}
```

Both fields default `null` → existing constructor calls compile unchanged. `SqlStorageDriver` (M-006-shipped translatable write path) backfills the array; non-translatable writes leave it null.

## Cache-tag canonical vocabulary (FR-034, FR-023)

| Tag pattern | When emitted | Invalidated by |
|---|---|---|
| `entity:<type>` | Always for each row's entity type | Any save/delete of any entity of that type |
| `entity:<type>:<id>` | Per row in `cacheTags()` | Save/delete of that specific entity |
| `entity:<type>:<id>:<langcode>` | Per row for translatable types, per langcode in `affectedLangcodes` | Save/delete that touched that langcode |

## Context-resolver behavior matrix

| Context name | Source | Returns | Empty/missing → |
|---|---|---|---|
| `user.roles` | `RequestContext::roles()` | sorted-asc string IDs joined with `,` | empty string |
| `user.id` | `RequestContext::accountId()` | integer ID as string | empty string |
| `url.query.<param>` | `$request->getQueryParams()[$param]` | URL-decoded value | empty string |
| `language.content` | `RequestContext::activeLangcode()` | langcode | empty string |
| `language.interface` | `RequestContext::interfaceLangcode()` | langcode | empty string |

Unknown context names log a warning and return empty string (per R-11; resolver bypasses cache for that resolution).

## Resolution algorithm (normative — see spec §7.1)

Encoded in `ListingResolver::resolve()`. 12 steps; deterministic except for the cache layer (which is, by design, the only state-bearing component).

## Open design questions deferred to /spec-kitty.tasks

- Filter / Sort value-object handling under JSON serialisation for `var/manifest.php` caching (R-12: tag-string format is documented; the value-object serializer ergonomics live in WP01's task brief).
- Whether `ListingResolver` should emit a `ListingResolvedEvent` for observability (out of v0.x scope; flag for v1.x).
- Whether `MemoryBackend::invalidateByTag` should be batched (currently linear in tag-index size; acceptable for v0.x).
