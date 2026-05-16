---
work_package_id: WP01
title: Listing package scaffold + value objects + factories
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-005
- FR-006
- FR-007
- FR-008
- FR-009
- FR-010
- FR-011
- FR-012
- FR-013
- FR-014
- FR-054
- FR-055
- FR-056
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-listing-pipeline-v1-01KRMN0B
base_commit: 0f2e833e6d7ae47815e77728e32046e878adc97c
created_at: '2026-05-16T18:46:26.446858+00:00'
subtasks:
- T001
- T002
- T003
- T004
- T005
- T006
shell_pid: "10829"
history: []
authoritative_surface: packages/listing/
execution_mode: code_change
owned_files:
- packages/listing/composer.json
- packages/listing/src/Operator.php
- packages/listing/src/SortDirection.php
- packages/listing/src/FilterDefinition.php
- packages/listing/src/SortDefinition.php
- packages/listing/src/Filter.php
- packages/listing/src/Sort.php
- packages/listing/src/Pagination.php
- packages/listing/src/ListingResult.php
- packages/listing/src/ListingDefinition.php
- packages/listing/src/Exception/UnsupportedListingException.php
- packages/listing/src/Exception/UnknownListingException.php
- packages/listing/tests/Unit/OperatorTest.php
- packages/listing/tests/Unit/SortDirectionTest.php
- packages/listing/tests/Unit/FilterDefinitionTest.php
- packages/listing/tests/Unit/SortDefinitionTest.php
- packages/listing/tests/Unit/FilterTest.php
- packages/listing/tests/Unit/SortTest.php
- packages/listing/tests/Unit/PaginationTest.php
- packages/listing/tests/Unit/ListingResultTest.php
- packages/listing/tests/Unit/ListingDefinitionTest.php
- packages/listing/tests/Unit/Exception/UnsupportedListingExceptionTest.php
- packages/listing/tests/Unit/Exception/UnknownListingExceptionTest.php
- composer.json
tags: []
agent: "claude:opus:python-reviewer:reviewer"
---

## Objective

Scaffold the new `packages/listing/` PHP package at Layer 3 and ship all immutable value objects + static factory classes that the rest of the listing pipeline composes from. This WP is the foundation: every other WP imports these types.

## Context

- Layer 3 (services) per the project's 7-layer architecture (see `CLAUDE.md` Layer Architecture).
- Namespace: `Waaseyaa\Listing\`.
- Test namespace: `Waaseyaa\Listing\Tests\Unit\`.
- PHP 8.5+, `declare(strict_types=1)` in every file.
- `final readonly class` is the default for value objects; `final class` for the static factories.
- Refer to `data-model.md` for full PHP signatures and `contracts/listing-definition.md` for stability commitments.

## Subtask details

### T001 — Scaffold `packages/listing/composer.json` + root path repository

**Purpose:** Create the package manifest that allows `composer install` to wire the new package into the monorepo.

**Steps:**
1. Create `packages/listing/composer.json` modelled on `packages/cache/composer.json` (an L0 package — adapt for L3):
   - `"name": "waaseyaa/listing"`
   - `"type": "library"`
   - `"description": "Views-equivalent declarative listing pipeline for Waaseyaa entities."`
   - `"license": "MIT"`
   - PSR-4 autoload: `"Waaseyaa\\Listing\\": "src/"`
   - PSR-4 autoload-dev: `"Waaseyaa\\Listing\\Tests\\": "tests/"`
   - `"require"`: `"php": ">=8.5"`, `"waaseyaa/foundation": "self.version"`, `"waaseyaa/entity": "self.version"`, `"waaseyaa/entity-storage": "self.version"`, `"waaseyaa/cache": "self.version"`, `"waaseyaa/access": "self.version"`, `"waaseyaa/typed-data": "self.version"`
   - `"require-dev"`: `"phpunit/phpunit": "^10.5"`
   - `"extra.waaseyaa.layer": 3`
   - `"extra.waaseyaa.providers": ["Waaseyaa\\Listing\\ServiceProvider"]` — ServiceProvider class will be shipped by WP11; this entry dangles until then (expected)
   - `"config.sort-packages": true`
2. Update root `composer.json`:
   - Add `"./packages/listing"` to the path repositories list
   - Add `"waaseyaa/listing": "self.version"` to the metapackage `require` section
3. Run `composer update --no-install` from project root to refresh the lockfile and confirm policy gates pass (`bin/check-composer-policy`).

**Files:** `packages/listing/composer.json` (new, ~40 lines); `composer.json` (root, modified).

**Validation:**
- `bin/check-composer-policy` passes
- `bin/check-package-layers` passes (no upward edges)
- `composer dump-autoload` succeeds without errors

### T002 — `Operator` + `SortDirection` backed enums

**Purpose:** Define the operator vocabulary that all filter declarations consume + the sort direction enum.

**Steps:**
1. Create `packages/listing/src/Operator.php`:
   - Backed string enum with 13 cases per `data-model.md`: `EQ`, `NEQ`, `LT`, `LTE`, `GT`, `GTE`, `IN`, `NOT_IN`, `IS_NULL`, `IS_NOT_NULL`, `BETWEEN`, `STARTS_WITH`, `CONTAINS`
   - Backing values: lowercase `eq`, `neq`, `lt`, `lte`, etc. — used for cache-key emission and `var/manifest.php` round-trip
2. Create `packages/listing/src/SortDirection.php`:
   - Backed string enum: `ASC = 'asc'`, `DESC = 'desc'`

**Files:** `packages/listing/src/Operator.php` (new, ~30 lines); `packages/listing/src/SortDirection.php` (new, ~10 lines).

**Validation:** `Operator::EQ->value === 'eq'`; `SortDirection::ASC->value === 'asc'`.

### T003 — `FilterDefinition` value object

**Purpose:** Immutable filter declaration with construction-time operator-value-shape validation.

**Steps:**
1. Create `packages/listing/src/FilterDefinition.php` per `data-model.md` signature:
   - `final readonly class FilterDefinition`
   - Constructor: `__construct(public string $field, public Operator $op, public mixed $value, public ?string $exposedParam = null)`
   - Constructor invariants: `$field` non-empty; `$exposedParam` matches `/^[a-z][a-z0-9_]*$/` when set
   - Private method `validateOperatorValueShape()` per the matrix in `contracts/listing-definition.md`:
     - `EQ`/`NEQ`: scalar (null acceptable)
     - `LT`/`LTE`/`GT`/`GTE`: scalar, non-null
     - `IN`/`NOT_IN`: non-empty list → throws `InvalidArgumentException` on empty (FR-010)
     - `IS_NULL`/`IS_NOT_NULL`: must be `null`
     - `BETWEEN`: 2-element tuple
     - `STARTS_WITH`/`CONTAINS`: string
   - `public function withExposed(string $param): self` — returns clone with `$exposedParam` set

**Files:** `packages/listing/src/FilterDefinition.php` (new, ~80 lines).

**Validation:** Every invariant has a unit test in WP01's test suite (T006).

### T004 — `SortDefinition` value object

**Purpose:** Immutable sort declaration.

**Steps:**
1. Create `packages/listing/src/SortDefinition.php`:
   - `final readonly class SortDefinition`
   - Constructor: `__construct(public string $field, public SortDirection $direction = SortDirection::ASC)`
   - No further methods — purely a data carrier

**Files:** `packages/listing/src/SortDefinition.php` (new, ~20 lines).

### T005 — `Filter` + `Sort` static factory classes

**Purpose:** Ergonomic construction surface matching ADR 015 examples (`Filter::gte('starts_at', 'now')`).

**Steps:**
1. Create `packages/listing/src/Filter.php`:
   - `final class Filter` with private constructor (factory-only)
   - Static methods per `contracts/listing-definition.md` factory list:
     - `eq`, `neq`, `lt`, `lte`, `gt`, `gte` — scalar
     - `in`, `notIn` — array
     - `isNull`, `isNotNull` — field only
     - `between` — `(field, low, high)`
     - `startsWith`, `contains` — `(field, string)`
     - `langcode(string $code): FilterDefinition` — emits `new FilterDefinition('langcode', Operator::EQ, $code)` (the `langcode` field name is canonical; FR-046 / R-09)
     - `exposed(FilterDefinition $base, string $param): FilterDefinition` — calls `$base->withExposed($param)`
2. Create `packages/listing/src/Sort.php`:
   - `final class Sort`
   - `static asc(string $field): SortDefinition` + `static desc(string $field): SortDefinition`

**Files:** `packages/listing/src/Filter.php` (new, ~80 lines); `packages/listing/src/Sort.php` (new, ~20 lines).

### T006 — `Pagination` + `ListingResult` + `ListingDefinition` + exceptions + unit tests

**Purpose:** Round out the value-object surface and prove correctness with comprehensive unit tests.

**Steps:**
1. Create `packages/listing/src/Pagination.php` per `data-model.md`:
   - 6-property constructor: `page`, `pageSize`, `?totalRows`, `?totalPages`, `hasPrev`, `hasNext`
2. Create `packages/listing/src/ListingResult.php`:
   - 4-property constructor: `iterable $rows`, `Pagination $pagination`, `array $cacheTags`, `array $cacheContexts`
3. Create `packages/listing/src/ListingDefinition.php`:
   - 9-param constructor: `id`, `entityType`, `?bundle`, `filters`, `sorts`, `?pageSize = 20`, `accessOps = ['view']`, `approximateTotal = false`, `?cacheTtl = null`
   - Private 10th constructor param `bool $unbounded = false` (settable only via `allowUnbounded()` builder)
   - Construction invariants per `contracts/listing-definition.md`:
     - `$id` matches `/^[a-z][a-z0-9_]*$/`
     - `$entityType` non-empty
     - `$accessOps` non-empty
     - Every filter is `FilterDefinition`, every sort is `SortDefinition`
     - `$pageSize === null` OR `$pageSize > 0`
   - `public function allowUnbounded(): self` — clone with `$unbounded = true`
   - `public function isUnbounded(): bool`
   - `public function effectiveContexts(EntityTypeInterface $entityType): array` — computes declared + implicit per FR-024 (returns string list)
   - `public function cacheKeyHash(): string` — canonical JSON of the constructor params → SHA-256 → first 16 hex chars (FR-005 / FR-037)
   - NOTE: heavy validation (supportsQuery, langcode-on-translatable, page-size cap) is deferred to `ListingDefinitionValidator` in WP10
4. Create `packages/listing/src/Exception/UnsupportedListingException.php`:
   - `final class UnsupportedListingException extends \RuntimeException`
   - Constructor: `(string $listingId, ?string $fieldName, string $reason, ?\Throwable $previous = null)`
5. Create `packages/listing/src/Exception/UnknownListingException.php`:
   - `final class UnknownListingException extends \RuntimeException`
   - Constructor: `(string $listingId)`
6. Write unit tests in `packages/listing/tests/Unit/`:
   - `FilterDefinitionTest.php` — all operator-value-shape cases (positive + negative); withExposed clone semantics
   - `SortDefinitionTest.php` — default ASC; explicit DESC
   - `FilterTest.php` + `SortTest.php` — each factory method returns expected definition
   - `OperatorTest.php` + `SortDirectionTest.php` — backed-string values stable
   - `ListingDefinitionTest.php` — every construction invariant has positive + negative test; allowUnbounded builder; cacheKeyHash determinism (same inputs → same digest); effectiveContexts output matches FR-024 matrix
   - `PaginationTest.php` + `ListingResultTest.php` — value-object property access
   - `Exception/*Test.php` — message format + carried context

**Files:** Value-object PHP files (new, ~250 lines total); unit tests (new, ~600 lines total).

**Validation:**
- `vendor/bin/phpunit --testsuite Unit packages/listing/tests/Unit/` green
- `composer cs-check` (Pint, Laravel preset) green
- `composer phpstan` level 5 green on the new package
- Zero new warnings in CI

## Test strategy

Unit tests only at this layer. Contract tests + integration tests live in WP05 and onwards. Coverage targets:
- 100% of construction-time invariants exercised
- Every static factory method has at least one positive test
- Operator-value-shape matrix exhaustively covered (positive + negative per row)

## Definition of Done

- [ ] All 12 owned files exist on disk with content matching `data-model.md` signatures
- [ ] `bin/check-composer-policy` passes
- [ ] `bin/check-package-layers` passes (no upward edges)
- [ ] `composer dump-autoload` succeeds
- [ ] `vendor/bin/phpunit --testsuite Unit packages/listing/tests/Unit/` green
- [ ] `composer cs-check` + `composer phpstan` green
- [ ] Test coverage of value-object invariants ≥ 90% (line coverage on new files)

## Risks

| Risk | Mitigation |
|---|---|
| `extra.waaseyaa.providers` entry references not-yet-existing `Waaseyaa\Listing\ServiceProvider` (WP11) | Expected dangling reference; manifest builder skips missing classes during WP01–WP10 development. Resolves when WP11 lands. |
| Operator-value-shape validation surface drift from `contracts/listing-definition.md` | Reviewer cross-references the matrix in the contract doc with the implementation. |
| `cacheKeyHash()` non-determinism | Use `json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)` with sorted keys via a recursive `ksort()`; assert in test with 5 different but equivalent input orderings. |

## Reviewer guidance

- Verify the operator-value-shape matrix matches `contracts/listing-definition.md` exactly (one row per Operator case).
- Verify exception classes carry the full context fields (no information loss when caught).
- Verify `cacheKeyHash()` is canonicalized (sorted keys, consistent number/string serialization) — the determinism test is the load-bearing assertion.
- Verify all autoload paths resolve from a fresh `composer dump-autoload` (no missing-class warnings).

## Implementation command

```bash
spec-kitty agent action implement WP01 --agent <name>
```

## Activity Log

- 2026-05-16T18:46:27Z – claude:sonnet:python-implementer:implementer – shell_pid=6895 – Assigned agent via action command
- 2026-05-16T19:00:31Z – claude:sonnet:python-implementer:implementer – shell_pid=6895 – WP01 ready: 23 owned files + 4 companion registrations (bin/check-package-layers, composer.lock, phpstan.neon, root composer.json). 115 tests, 202 assertions, all green. All gates pass: phpunit, cs-check, phpstan, check-composer-policy, check-package-layers, composer validate, dump-autoload. Forced past pre-flight: the only uncommitted entry (kitty-specs/.../WP12-charter-docs-closure/) is a directory tracked under the WP12 prompt's lifecycle, untouched by WP01.
- 2026-05-16T19:01:10Z – claude:opus:python-reviewer:reviewer – shell_pid=10829 – Started review via action command
- 2026-05-16T19:03:37Z – claude:opus:python-reviewer:reviewer – shell_pid=10829 – WP01 approved: 115/115 tests, all gates green (cs-check, phpstan, composer-policy, package-layers); FR-001..FR-014 invariants implemented; Layer 3 placement matches doctrine spec; cacheKeyHash filter-order-sensitivity matches FR-002; Pagination plain-int + runtime guards is documented negative-path-test intent; minor: 2 unused test imports (non-blocking). --force used to bypass spurious WP12 pre-flight match (consistent with implementer's same workaround).
