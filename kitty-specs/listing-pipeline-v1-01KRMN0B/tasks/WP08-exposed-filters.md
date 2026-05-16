---
work_package_id: WP08
title: ExposedFilterParser + ExposedFilterValues + ExposedFilterCoercer + permissive/strict modes
dependencies:
- WP01
requirement_refs:
- FR-042
- FR-043
- FR-044
- FR-045
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T038
- T039
- T040
- T041
- T042
history: []
authoritative_surface: packages/listing/
execution_mode: code_change
owned_files:
- packages/listing/src/ExposedFilterValues.php
- packages/listing/src/ExposedFilterCoercer.php
- packages/listing/src/ExposedFilterParser.php
- packages/listing/src/Exception/ListingCoercionException.php
- packages/listing/tests/Unit/ExposedFilterValuesTest.php
- packages/listing/tests/Unit/ExposedFilterCoercerTest.php
- packages/listing/tests/Unit/ExposedFilterParserTest.php
tags: []
agent: "claude:sonnet:python-implementer:implementer"
shell_pid: "35620"
---

## Objective

Ship URL-bound filter parsing: take a request's `$_GET`-equivalent params and a `ListingDefinition`, return a typed `ExposedFilterValues` map that the resolver consumes. Two modes: permissive (production — silent-drop on coercion failure) and strict (test envs — throws `ListingCoercionException`).

## Context

- Stable surface: `ExposedFilterParser` + `ExposedFilterValues` are charter §5.X.
- `ExposedFilterCoercer` is INTERNAL — implementation detail subject to refactor.
- `ListingCoercionException` is INTERNAL — production callers must catch and silent-drop per FR-044.
- Refer to `contracts/exposed-filters.md` for the full coercion matrix.

## Subtask details

### T038 — `ExposedFilterValues` value object

**Steps:**
1. Create `packages/listing/src/ExposedFilterValues.php`:
   ```php
   namespace Waaseyaa\Listing;

   final readonly class ExposedFilterValues
   {
       /** @param array<non-empty-string, mixed> $values */
       public function __construct(private array $values = []) {}

       public function get(string $param): mixed { return $this->values[$param] ?? null; }
       public function has(string $param): bool { return array_key_exists($param, $this->values); }
       public function all(): array { return $this->values; }

       public function cacheKeyHash(): string
       {
           $sorted = $this->values;
           ksort($sorted);
           return substr(
               hash('sha256', json_encode($sorted, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
               0,
               16,
           );
       }
   }
   ```

**Files:** `packages/listing/src/ExposedFilterValues.php` (new, ~35 lines).

### T039 — `ExposedFilterCoercer` per-operator coercion

**Steps:**
1. Create `packages/listing/src/ExposedFilterCoercer.php`:
   - `final class ExposedFilterCoercer`
   - `public function coerce(string $raw, Operator $op, string $typedDataType): mixed` — throws `ListingCoercionException` on failure
2. Coercion matrix (per `contracts/exposed-filters.md`):
   - Scalar operators (`EQ`, `NEQ`, `LT`, `LTE`, `GT`, `GTE`):
     - Use `Waaseyaa\TypedData\Coercer::coerce($raw, $typedDataType)` (assume this exists; if not, implement minimum coercion: int via `filter_var(... FILTER_VALIDATE_INT)`, bool via `filter_var(... FILTER_VALIDATE_BOOLEAN)`, float via similar, string passthrough, DateTime via `new DateTimeImmutable($raw)` with try/catch)
     - Throw `ListingCoercionException` on coercion failure
   - `IN`/`NOT_IN`:
     - Split `$raw` on `,`
     - Coerce each element per `$typedDataType`
     - Return as `list`; throw if any element fails
   - `BETWEEN`:
     - Split `$raw` on `~`
     - Expect exactly 2 parts
     - Coerce both per `$typedDataType`
     - Return `[low, high]` tuple
   - `IS_NULL`/`IS_NOT_NULL`:
     - Non-empty `$raw` means "filter applies"
     - Return `null` (the operator carries the semantics)
   - `STARTS_WITH`/`CONTAINS`:
     - Return `$raw` as-is (string; URL-decoded once)
3. Common helper: `coerceScalar(string $raw, string $type): mixed` for the repeated scalar-coercion logic.

**Files:** `packages/listing/src/ExposedFilterCoercer.php` (new, ~120 lines).

### T040 — `ListingCoercionException`

**Steps:**
1. Create `packages/listing/src/Exception/ListingCoercionException.php`:
   ```php
   namespace Waaseyaa\Listing\Exception;
   final class ListingCoercionException extends \RuntimeException
   {
       public function __construct(
           public readonly string $raw,
           public readonly string $operatorName,
           public readonly string $expectedType,
           ?\Throwable $previous = null,
       ) {
           parent::__construct(
               \sprintf('Coercion failure: raw=%s, operator=%s, type=%s', $raw, $operatorName, $expectedType),
               0,
               $previous,
           );
       }
   }
   ```

**Files:** `packages/listing/src/Exception/ListingCoercionException.php` (new, ~30 lines).

### T041 — `ExposedFilterParser` (permissive + strict modes)

**Steps:**
1. Create `packages/listing/src/ExposedFilterParser.php`:
   - `final class ExposedFilterParser`
   - Constructor: `__construct(private readonly ExposedFilterCoercer $coercer, private readonly LoggerInterface $logger = new NullLogger(), private readonly bool $strict = false)`
   - Static `public static function create(): self` returns `new self(new ExposedFilterCoercer())`
   - Method `public function strict(): self` returns `new self($this->coercer, $this->logger, strict: true)` (fluent factory; same internal coercer + logger, strict flag flipped)
   - Method `public function parse(array $queryParams, ListingDefinition $def): ExposedFilterValues`:
     - `$values = []`
     - For each `FilterDefinition $f` in `$def->filters`:
       - If `$f->exposedParam === null`: skip
       - `$raw = $queryParams[$f->exposedParam] ?? null`
       - If `$raw === null` or `$raw === ''`: skip (filter not applied)
       - Try: `$coerced = $this->coercer->coerce($raw, $f->op, /* typed-data type of $f->field */)`
         - Note: the typed-data type comes from the entity-type's field definition. The parser may need an `EntityTypeManager` injection OR the `ListingDefinition` passes the type per filter. Design choice: have `FilterDefinition` carry an optional `?string $valueType` set at construction-time by `Filter::*` factories OR look it up in resolver. Simplest: pass `$valueType` as a parser-injection that resolves per-filter. Coordinate with WP05's filter handling.
       - Catch `ListingCoercionException`:
         - If `$this->strict`: rethrow
         - Else: `$this->logger->debug('exposed filter coercion failed', ...)` + continue (skip filter)
       - On success: `$values[$f->exposedParam] = $coerced`
     - Return `new ExposedFilterValues($values)`

**Files:** `packages/listing/src/ExposedFilterParser.php` (new, ~80 lines).

### T042 — Parser + coercer unit tests

**Steps:**
1. `ExposedFilterValuesTest.php`:
   - `getReturnsValueOrNull`
   - `hasReturnsTrueForPresentKey`
   - `allReturnsFullMap`
   - `cacheKeyHashIsDeterministic`
   - `cacheKeyHashKeyOrderInvariant`
2. `ExposedFilterCoercerTest.php`:
   - One test per coercion path (positive + negative):
     - `coercesIntFromString`
     - `coercesBoolFromString`
     - `coercesDateTime`
     - `throwsOnIntCoercionFailure`
     - `splitsInValuesOnComma`
     - `splitsBetweenOnTilde`
     - `betweenRejectsWrongTupleLength`
     - `isNullReturnsNullRegardlessOfRaw`
     - `startsWithReturnsRawString`
3. `ExposedFilterParserTest.php`:
   - `parseExtractsDeclaredParams`
   - `parseIgnoresNonDeclaredParams`
   - `parseSkipsEmptyValues`
   - `permissiveModeDropsOnCoercionFailure` (logger called at debug level)
   - `strictModeThrowsOnCoercionFailure` (asserts `ListingCoercionException`)
   - `strictReturnsNewInstance` (`$parser->strict() !== $parser`)

**Files:** Tests (~300 lines total).

## Test strategy

Unit tests only at this layer. Integration with `ListingResolver` (passing parsed values into `resolve()`) is exercised in WP11 integration tests.

## Definition of Done

- [ ] All 4 source files + 3 test files exist
- [ ] All operator coercion paths have positive + negative tests
- [ ] Strict vs permissive mode distinction verified
- [ ] `composer cs-check` + `composer phpstan` green

## Risks

| Risk | Mitigation |
|---|---|
| Dependency on `Waaseyaa\TypedData\Coercer` that may not exist | Read `packages/typed-data/src/` first to confirm. If missing, implement minimum coercion inline + flag follow-up for typed-data widening |
| LIKE-pattern escape responsibility unclear | Per `contracts/exposed-filters.md`: parser does NOT escape; SQL emitter does. Test asserts parser returns raw string for STARTS_WITH/CONTAINS |
| Strict mode unexpectedly leaks to production | `strict()` is a fluent factory returning a new instance — production code path uses `ExposedFilterParser::create()` without `->strict()`. Test that production-shaped construction has `$strict === false` |

## Reviewer guidance

- Verify the strict-vs-permissive flag is on the INSTANCE, not a method parameter (so the choice is set at DI time and immutable per request).
- Verify the parser ignores `?page=N` (it's consumed by the resolver, not the parser).
- Verify coercion-failure path drops the filter SILENTLY in permissive mode — no exception propagation.
- Verify `cacheKeyHash()` is deterministic across PHP runs (ksort + JSON_UNESCAPED_SLASHES).

## Implementation command

```bash
spec-kitty agent action implement WP08 --agent <name>
```

## Activity Log

- 2026-05-16T20:40:38Z – claude:sonnet:python-implementer:implementer – shell_pid=35620 – Started implementation via action command
- 2026-05-16T20:46:17Z – claude:sonnet:python-implementer:implementer – shell_pid=35620 – WP08 ready: ExposedFilterParser + Coercer + permissive/strict modes. All gates green.
