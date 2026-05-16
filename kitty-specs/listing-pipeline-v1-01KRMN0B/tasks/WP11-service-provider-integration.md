---
work_package_id: WP11
title: ServiceProvider DI wiring + reference consumer fixture + Phase14 integration tests
dependencies:
- WP01
- WP02
- WP03
- WP04
- WP05
- WP06
- WP07
- WP08
- WP09
- WP10
requirement_refs:
- FR-052
- FR-053
- NFR-005
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T051
- T052
- T053
- T054
- T055
history: []
authoritative_surface: packages/listing/
execution_mode: code_change
owned_files:
- packages/listing/src/ServiceProvider.php
- packages/listing/tests/Fixtures/UpcomingEventsListing.php
- packages/listing/tests/Fixtures/EventEntity.php
- tests/Integration/Phase14/ListingPipelineIntegrationTest.php
- tests/Integration/Phase14/ListingCacheInvalidationIntegrationTest.php
tags: []
agent: "claude:sonnet:python-implementer:implementer"
shell_pid: "46117"
---

## Objective

Close the implementation loop: ship the `ServiceProvider` that DI-binds every listing-pipeline service, demonstrate the full flow with a reference consumer fixture, and prove end-to-end behavior in two integration tests. This WP makes M-007 actually work in a running app.

## Context

- Depends on every prior WP — runs the last implementation step before docs (WP12).
- `composer.json` (WP01) already references this class via `extra.waaseyaa.providers`.
- ServiceProvider follows the existing framework convention (look at `packages/cache/src/ServiceProvider.php` or `packages/cli/src/ServiceProvider.php` for the canonical shape).

## Subtask details

### T051 — `packages/listing/src/ServiceProvider.php`

**Steps:**
1. Create `packages/listing/src/ServiceProvider.php`:
   - `final class ServiceProvider extends \Waaseyaa\Foundation\ServiceProvider`
   - `public function register(): void`:
     - Bind `ListingDiscoverer` (collects from registered `HasListingsInterface` providers)
     - Bind `ListingDefinitionRegistry` (build by calling `discoverer->discover()` and indexing by id)
     - Bind `ListingResolver` with full DI args from the container
     - Bind `ListingCacheKeyBuilder` (no-arg constructor — easy)
     - Bind `ListingCacheInvalidator` (with `TaggedCacheInterface` + `LoggerInterface`)
     - Bind `ExposedFilterParser` (default permissive — `ExposedFilterParser::create()`)
     - Bind `ListingDefinitionValidator`
   - `public function boot(): void`:
     - Register `ListingCacheInvalidator` as event listener on `AfterSaveEvent` + `AfterDeleteEvent` at priority=100 (if framework uses attribute discovery this is automatic; otherwise explicit subscriber registration here)
     - Register canonical context names with `ContextRegistry` (idempotent — already seeded; this is a no-op safety net)
     - Run `ListingDefinitionValidator::validate($this->container->get(ListingDefinitionRegistry::class))` — throws on first failure, kernel boot halts

**Files:** `packages/listing/src/ServiceProvider.php` (new, ~120 lines).

### T052 — ServiceProvider boot-phase wiring details

**Purpose:** Validator integration with `PackageManifestCompiler::warm()`.

**Steps:**
1. Confirm whether `ServiceProvider::boot()` runs after manifest warm or before. Per FR-052: validator runs after entity-type registration but before route dispatch.
2. If `boot()` is post-manifest-warm: validator call in `boot()` is the right place.
3. If `boot()` runs earlier: hook into `PackageManifestCompiler::warm()` via attribute or explicit subscriber.
4. The detail of validator timing was R-02 in research.md — boot-time. Re-verify against framework's `PackageManifestCompiler` execution order at implementation time.

**Files:** Continued in `ServiceProvider.php`.

### T053 — Reference consumer fixture

**Purpose:** Demonstrate the full happy path with a self-contained example.

**Steps:**
1. Create `packages/listing/tests/Fixtures/EventEntity.php`:
   - `final class EventEntity extends ContentEntityBase`
   - Hardcodes `entityTypeId = 'event'` + entity keys `id` / `title` / `starts_at` / `category`
   - Constructor signature: `public function __construct(array $values = [])` per the entity-storage convention
2. Create `packages/listing/tests/Fixtures/UpcomingEventsListing.php`:
   - `final class UpcomingEventsListing implements HasListingsInterface`
   - `public function listings(): array` returns:
     ```php
     return [
         new ListingDefinition(
             id: 'upcoming_events',
             entityType: 'event',
             filters: [
                 Filter::gte('starts_at', 'now'),
                 Filter::exposed(Filter::eq('category', null), 'category'),
             ],
             sorts: [Sort::asc('starts_at')],
             pageSize: 20,
             accessOps: ['view'],
         ),
     ];
     ```

**Files:** Both fixture files (~80 lines total).

### T054 — `ListingPipelineIntegrationTest`

**Steps:**
1. Create `tests/Integration/Phase14/ListingPipelineIntegrationTest.php`:
   - Boot a test kernel that registers `UpcomingEventsListing` fixture provider
   - Seed several `event` entities — some past, some future, some in different categories
   - Test cases:
     - `resolveReturnsOnlyFutureEvents` — gte filter works against seeded data
     - `resolveAppliesExposedCategoryFilter` — pass `?category=teaching` → only matching rows
     - `resolveRespectsPageSize20` — page 1 returns ≤ 20 rows
     - `resolveReturnsCorrectPaginationMetadata` — page count, totalRows, hasNext
     - `cacheTagsAndContextsOnResult` — assert tag list + context list shape

**Files:** Test (~200 lines).

### T055 — `ListingCacheInvalidationIntegrationTest`

**Steps:**
1. Create `tests/Integration/Phase14/ListingCacheInvalidationIntegrationTest.php`:
   - Boot kernel with translatable variant of the fixture (an event with `en` + `mi-tle` translations)
   - Sequence:
     1. Resolve listing → cache MISS (populate)
     2. Resolve listing → cache HIT (assert same result via identity / cached path)
     3. Save an event entity touching `en` + `mi-tle` langcodes
     4. Resolve listing → cache MISS again (invalidated)
     5. Assert the event's update is visible in the new result
   - Inspect tag emission:
     - After step 3, assert the cache no longer has entries for tags `entity:event:42:en` and `entity:event:42:mi-tle`

**Files:** Test (~200 lines).

## Test strategy

Integration tests boot a real kernel — no mocking of resolver / cache / invalidator. The reference consumer fixture is also used by these tests, doubling as both demo + test data.

## Definition of Done

- [ ] `ServiceProvider` exists and DI-binds every M-007 service
- [ ] Fixture entity + listing exist in `packages/listing/tests/Fixtures/`
- [ ] Both Phase14 integration tests pass against a real kernel boot
- [ ] `vendor/bin/phpunit tests/Integration/Phase14/Listing*` green
- [ ] `bin/check-package-layers` green
- [ ] `composer cs-check` + `composer phpstan` green

## Risks

| Risk | Mitigation |
|---|---|
| ServiceProvider DI wiring drifts from existing convention | Read `packages/cache/src/ServiceProvider.php` first; mirror exactly. Don't invent new patterns |
| Phase14 directory doesn't exist yet (current ceiling may be Phase13) | Verify with `ls tests/Integration/`; create the directory if needed; cross-reference plan.md project structure |
| Event listener priority not honored under real framework dispatch | Test asserts ordering by setting up two listeners and checking call order |
| Fixture entity needs translation support — depends on M-006-shipped infrastructure | All M-006 deliverables are on main (verified during planning); reference the shipped types |
| Validator throws on fixture listing during boot (causing test setup failure) | Test fixture validates: simple def, no exotic configs. If validator rejects, it's a real bug in the fixture |

## Reviewer guidance

- Verify ServiceProvider mirrors existing framework `ServiceProvider` conventions (don't introduce drift).
- Verify `boot()` calls the validator AFTER the registry is built.
- Verify the reference fixture is realistic (matches the quickstart.md scenario; consumers reading the cookbook see the same shape).
- Verify integration tests boot a hermetic kernel (no shared state across tests).
- Verify cache invalidation integration test asserts BOTH the miss-after-save AND the new content visibility — not just the miss.

## Implementation command

```bash
spec-kitty agent action implement WP11 --agent <name>
```

## Activity Log

- 2026-05-16T21:16:34Z – claude:sonnet:python-implementer:implementer – shell_pid=46117 – Started implementation via action command
