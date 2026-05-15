# Contract: ExposedFilterParser + ExposedFilterValues + ExposedFilterCoercer

**Stability scope:** Charter §5.X (parser, values); coercer is INTERNAL
**FRs covered:** FR-042..FR-045
**Owned by:** WP09

## ExposedFilterParser

```php
final class ExposedFilterParser
{
    public function __construct(
        private readonly ExposedFilterCoercer $coercer,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $strict = false,
    );

    public static function create(): self;

    public function strict(): self;

    /**
     * @param array<string, mixed> $queryParams  the request's $_GET-equivalent
     */
    public function parse(array $queryParams, ListingDefinition $def): ExposedFilterValues;
}
```

**Modes:**

| Mode | Default | On coercion failure |
|---|---|---|
| Permissive (production) | yes | Drop the filter; debug-log via `LoggerInterface`; resolver runs without that filter |
| Strict (test envs) | opt-in via `strict()` | Throw `ListingCoercionException` |

**Parse contract (FR-042..FR-045):**
1. For each `FilterDefinition $f` in `$def->filters` where `$f->exposedParam !== null`:
   1. `$raw = $queryParams[$f->exposedParam] ?? null`
   2. If `$raw === null` OR `$raw === ''`: continue (filter not applied; default behaviour stands)
   3. Try `$coerced = $this->coercer->coerce($raw, $f->op, /* field's typed-data type */)`
   4. On `ListingCoercionException`: strict mode rethrows; permissive mode debug-logs + continues
   5. On success: record `$f->exposedParam => $coerced` in the values map
2. Other `$queryParams` keys (not named by any exposed filter) are ignored
3. Return `new ExposedFilterValues($valuesMap)`

**Other URL parameters reserved:**
- `?page=N` consumed by the resolver, not the parser. Parser does NOT include `page` in `ExposedFilterValues`.

## ExposedFilterValues

```php
final readonly class ExposedFilterValues
{
    public function __construct(private array $values = []);    // array<string, mixed>

    public function get(string $param): mixed;
    public function has(string $param): bool;
    public function all(): array;
    public function cacheKeyHash(): string;     // canonical-JSON → SHA-256 → 16 hex chars
}
```

**Stability commitment:** Method shape locked. Future additions are additive.

**Hash determinism:** `cacheKeyHash()` MUST produce the same digest for the same value map across PHP workers; canonical JSON sorts keys and uses fixed numeric / string serialisation.

## Coercer (INTERNAL — not stable surface)

```php
final class ExposedFilterCoercer
{
    /**
     * @throws ListingCoercionException
     */
    public function coerce(string $raw, Operator $op, string $typedDataType): mixed;
}
```

**Coercion matrix (FR-043):**

| Operator | Input format | Coerced value |
|---|---|---|
| `EQ`, `NEQ`, `LT`, `LTE`, `GT`, `GTE` | scalar string | per typed-data type (int / bool / string / DateTimeInterface) |
| `IN`, `NOT_IN` | comma-separated string (e.g. `?tags=a,b,c`) | `list<scalar>` per typed-data type |
| `BETWEEN` | `<low>~<high>` (e.g. `?date=2026-01-01~2026-12-31`) | `[low, high]` tuple per typed-data type |
| `IS_NULL`, `IS_NOT_NULL` | any non-empty value | `null` (the presence of the param is the signal) |
| `STARTS_WITH`, `CONTAINS` | raw string | string (URL-decoded once) |

**LIKE escaping:** Done inside the SQL emitter, NOT the parser. The parser passes the raw user-supplied string through.

## Exception (INTERNAL)

```php
final class ListingCoercionException extends \RuntimeException
{
    public function __construct(
        public readonly string $raw,
        public readonly Operator $op,
        public readonly string $expectedType,
        ?\Throwable $previous = null,
    );
}
```

## Test surface

- `exposedFilterCoercesIntFromString` (FR-043 happy path)
- `exposedFilterDropsOnCoercionFailure` (FR-044 silent-drop in permissive mode)
- `exposedFilterStrictModeThrowsOnCoercionFailure` (strict mode test path)
- `exposedFilterIgnoresNonDeclaredParams` (FR-042 — only declared params parsed)
- `exposedFilterEmptyValueLeavesFilterUnapplied` (parse step 1.ii)
- `exposedFilterValuesAreHashDeterministic` (FR-037 component)
