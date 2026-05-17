# Contract: ListingDefinition + FilterDefinition + SortDefinition + Operator

**Stability scope:** Charter §5.X (assigned at amendment time)
**FRs covered:** FR-001..FR-014
**Owned by:** WP01

## ListingDefinition

```php
final readonly class ListingDefinition
{
    public function __construct(
        public string $id,
        public string $entityType,
        public ?string $bundle = null,
        public array $filters = [],          // list<FilterDefinition>
        public array $sorts = [],            // list<SortDefinition>
        public ?int $pageSize = 20,
        public array $accessOps = ['view'],
        public bool $approximateTotal = false,
        public ?int $cacheTtl = null,
        private bool $unbounded = false,
    );

    public function allowUnbounded(): self;
    public function isUnbounded(): bool;
    public function effectiveContexts(EntityTypeInterface $entityType): array;
    public function cacheKeyHash(): string;
}
```

**Construction-time invariants (throw `InvalidArgumentException` on violation):**
- `id` matches `/^[a-z][a-z0-9_]*$/`
- `entityType` non-empty
- `accessOps` non-empty
- Every entry in `filters` is `FilterDefinition`
- Every entry in `sorts` is `SortDefinition`
- `pageSize === null` OR `pageSize > 0`

**Boot-time invariants (`ListingDefinitionValidator`, throw `UnsupportedListingException`):**
- `pageSize > 1000` AND `!isUnbounded()`
- `pageSize === null` AND `!isUnbounded()`
- `approximateTotal === true` AND `pageSize === null` AND `isUnbounded()`
- Entity type exists in `EntityTypeManager`
- Bundle (if set) exists for that entity type
- Every filter/sort field exists on the entity type
- Every filter/sort field's storage backend reports `supportsQuery() === true`
- Operator-to-field-type compatibility (BETWEEN requires comparable, etc.)
- Langcode filters/contexts only on translatable entity types

**Stability commitment:**
- All constructor parameters and accessors are stable from v0.x.
- `allowUnbounded()` builder is stable.
- `cacheKeyHash()` output format is stable (SHA-256 over canonical JSON → 16 hex chars).
- Future additions to the constructor MUST be optional with defaults.

## FilterDefinition

```php
final readonly class FilterDefinition
{
    public function __construct(
        public string $field,
        public Operator $op,
        public mixed $value,
        public ?string $exposedParam = null,
    );

    public function withExposed(string $param): self;
}
```

**Construction-time invariants:**
- `field` non-empty
- `exposedParam` matches `/^[a-z][a-z0-9_]*$/` when set
- Operator-to-value-type shape (see Operator section)

**Operator-to-value matrix:**

| Operator | Value type | Empty/null handling |
|---|---|---|
| `EQ`, `NEQ` | scalar | `null` value is valid (means "match against NULL"); other types are passthrough |
| `LT`, `LTE`, `GT`, `GTE` | scalar (comparable) | throws on `null` |
| `IN`, `NOT_IN` | non-empty list | throws on empty array (FR-010) |
| `IS_NULL`, `IS_NOT_NULL` | `null` | only `null` accepted |
| `BETWEEN` | `[low, high]` tuple (2-element list) | throws on length ≠ 2 |
| `STARTS_WITH`, `CONTAINS` | string | throws on non-string |

## SortDefinition

```php
final readonly class SortDefinition
{
    public function __construct(
        public string $field,
        public SortDirection $direction = SortDirection::ASC,
    );
}
```

Resolution always appends an implicit `SortDefinition($entityType->getKey('id'), ASC)` after user-declared sorts for stable pagination (FR-014).

## Operator (backed enum)

```php
enum Operator: string {
    case EQ = 'eq';   case NEQ = 'neq';
    case LT = 'lt';   case LTE = 'lte';
    case GT = 'gt';   case GTE = 'gte';
    case IN = 'in';   case NOT_IN = 'not_in';
    case IS_NULL = 'is_null';   case IS_NOT_NULL = 'is_not_null';
    case BETWEEN = 'between';
    case STARTS_WITH = 'starts_with';   case CONTAINS = 'contains';
}
```

Future operators are additive. Backing strings are stable (used in cache-key emission and `var/manifest.php` round-trip).

## Filter (factory)

```php
final class Filter {
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
    public static function langcode(string $code): FilterDefinition;          // FR-046
    public static function exposed(FilterDefinition $base, string $param): FilterDefinition;
}
```

## Sort (factory)

```php
final class Sort {
    public static function asc(string $field): SortDefinition;
    public static function desc(string $field): SortDefinition;
}
```

## Test surface (from spec §8.1)

`ListingResolverContract` covers operator semantics and validation rules:
- `resolveReturnsRowsMatchingFilters`
- `resolveReturnsEmptyOnNoMatch`
- `unsupportedFilterRaisesAtValidation`

Plus unit tests on the value-object classes themselves under `tests/Unit/`.
