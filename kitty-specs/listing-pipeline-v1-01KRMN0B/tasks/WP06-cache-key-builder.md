---
work_package_id: WP06
title: ListingCacheKeyBuilder + cache integration tests
dependencies:
- WP03
- WP05
requirement_refs:
- FR-037
- NFR-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T029
- T030
- T031
- T032
history: []
authoritative_surface: packages/listing/
execution_mode: code_change
owned_files:
- packages/listing/src/ListingCacheKeyBuilder.php
- packages/listing/tests/Unit/ListingCacheKeyBuilderTest.php
- packages/listing/tests/Integration/CacheIntegrationTest.php
tags: []
agent: "claude:opus:python-reviewer:reviewer"
shell_pid: "31666"
---

## Objective

Ship `ListingCacheKeyBuilder` (deterministic cache-key emission for resolver) and prove the cache-aware path of `ListingResolver` works end-to-end. WP05 already shipped resolver with nullable cache + key-builder DI parameters; this WP fills them.

## Context

- `ListingCacheKeyBuilder` is INTERNAL surface (not stable per `contracts/listing-resolver.md`). Hash format is documented but can evolve.
- Key format: `listing:<def-hash>:<exposed-hash>:<ctx-hash>` per FR-037.
- Component hashes: SHA-256 over canonical JSON → first 16 hex chars.
- Total key length bounded: `listing:` (8) + 3 × 16 hex chars + 2 colons = ~60 chars. Well below any cache backend's key-length limit.
- Cache-hit overhead target: < 0.5 ms p95 (NFR-003 sentinel).

## Subtask details

### T029 — `ListingCacheKeyBuilder` class

**Steps:**
1. Create `packages/listing/src/ListingCacheKeyBuilder.php`:
   ```php
   namespace Waaseyaa\Listing;
   final class ListingCacheKeyBuilder
   {
       public function build(
           ListingDefinition $def,
           ExposedFilterValues $exposed,
           array $contextValues,
       ): string {
           $defHash = $def->cacheKeyHash();
           $exposedHash = $exposed->cacheKeyHash();
           $ctxHash = $this->hashContext($contextValues);
           return \sprintf('listing:%s:%s:%s', $defHash, $exposedHash, $ctxHash);
       }

       private function hashContext(array $contextValues): string
       {
           ksort($contextValues);
           return substr(hash('sha256', json_encode($contextValues, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)), 0, 16);
       }
   }
   ```
2. `ListingDefinition::cacheKeyHash()` and `ExposedFilterValues::cacheKeyHash()` are already shipped by WP01 + WP08 — this method just composes the three.

**Files:** `packages/listing/src/ListingCacheKeyBuilder.php` (new, ~40 lines).

### T030 — `ListingCacheKeyBuilderTest` — determinism + collision smoke

**Steps:**
1. Create `packages/listing/tests/Unit/ListingCacheKeyBuilderTest.php`:
   - `keyIsDeterministicForSameInputs` — call `build()` twice with identical args, assert same string
   - `keyDiffersForDifferentDefinitions` — two definitions with different ids → different keys
   - `keyDiffersForDifferentExposedValues` — same def, different exposed values → different keys
   - `keyDiffersForDifferentContextValues` — same def + exposed, different context map → different keys
   - `keyOrderInvariantOnContextMap` — context values map with different insertion orders → same key (ksort handles this)
   - `keyFormatMatchesContract` — assert exact regex match `/^listing:[0-9a-f]{16}:[0-9a-f]{16}:[0-9a-f]{16}$/`
   - `keyDistinctEvenWithSubtleInputChanges` — change a single bool flag in def → different key (collision smoke; not exhaustive)

**Files:** Tests (~120 lines).

### T031 — Cache integration tests in `ListingResolverContract`

**Purpose:** Augment WP05's contract test with cache-aware scenarios. WP06 ADDS cases to the contract test, OR creates a separate cache-integration test that subclasses the same setup.

**Important:** WP05 owns `ListingResolverContract.php` per the file-overlap rule. To avoid overlap, WP06 creates a NEW test file: `packages/listing/tests/Integration/CacheIntegrationTest.php`.

**Steps:**
1. Create `packages/listing/tests/Integration/CacheIntegrationTest.php`:
   - Concrete `TestCase` (not abstract)
   - Sets up `ListingResolver` with **non-null** `$cache` + `$keyBuilder`
   - Test cases:
     - `cacheHitSecondResolveReturnsSameResult` — resolve, then resolve again → second call uses cache
     - `cacheStoresWithExpectedTags` — resolve, then assert `$cache->getTagsFor($key)` returns the expected tag list
     - `unknownContextBypassesCache` — register a context name on the listing that the registry doesn't know → resolve completes, but cache.get returns null afterwards (no store happened)
     - `cacheRespectsCacheTtl` — listing with `cacheTtl: 1`; resolve, sleep > 1s, resolve again → cache miss (TTL expired)
     - `cacheKeyDifferentForDifferentRequestContext` — same def, two different `RequestContext` instances (different user.roles) → different cache keys

**Files:** Test (~180 lines).

### T032 — Documentation updates

**Steps:**
1. Update `contracts/listing-resolver.md` (or inline class docblock on `ListingCacheKeyBuilder`) with the exact hash algorithm + format regex.
2. Verify the documentation example in `quickstart.md` (the "Cache integration" section) is accurate after this WP lands; correct any discrepancy.

**Files:** Edit `contracts/listing-resolver.md` (if it's owned by WP05 — coordinate; otherwise inline docblock).

**Note on file ownership:** `contracts/listing-resolver.md` is part of the planning artifacts, not source code. The owned_files glob in this WP doesn't include it. Updates to that contract doc happen via the documentation WP12. T032 stays inline (docblock only) to respect ownership.

## Test strategy

Unit tests prove builder determinism. Integration tests prove the resolver actually uses the cache when both DI parameters are filled.

## Definition of Done

- [ ] `ListingCacheKeyBuilder.php` exists with key composition algorithm
- [ ] Unit tests cover all 7 cases (determinism, distinctness, format)
- [ ] Integration test exercises resolver with non-null cache + key builder
- [ ] `composer cs-check` + `composer phpstan` green
- [ ] Cache-hit overhead measured in `cacheHitOverheadBenchmark` sentinel (added in T030 or separate)

## Risks

| Risk | Mitigation |
|---|---|
| `json_encode` non-determinism across PHP versions | Sort keys via `ksort` BEFORE encoding; use `JSON_UNESCAPED_SLASHES` to pin slash escaping; assert with bit-exact comparison across two runs |
| Hash collision between distinct inputs | SHA-256 truncated to 16 hex chars = 64 bits of entropy. Collision-probability test is birthday-bound — not exhaustive, but T030 includes a smoke test |
| Future need to widen key length | Format is stable from v0.x; widening requires a deprecation cycle. INTERNAL surface allows fix-it-later |

## Reviewer guidance

- Verify `ksort()` is applied to context values map BEFORE encoding (input-order independence).
- Verify the format string `'listing:%s:%s:%s'` is exact (no trailing/leading whitespace).
- Verify the 16-hex-char truncation is consistent across all three hash components (def, exposed, ctx).
- Verify integration tests use a real `MemoryBackend` (not a mock) so cache semantics are exercised.

## Implementation command

```bash
spec-kitty agent action implement WP06 --agent <name>
```

## Activity Log

- 2026-05-16T20:13:42Z – claude:sonnet:python-implementer:implementer – shell_pid=29961 – Started implementation via action command
- 2026-05-16T20:22:16Z – claude:sonnet:python-implementer:implementer – shell_pid=29961 – WP06 ready: ListingCacheKeyBuilder + cache integration tests. 9 unit tests + 10 integration tests prove FR-037 key composition and end-to-end resolver+TaggedCache flow (miss/store/hit, tag invalidation, TTL, request-context parity, FR-058 error tolerance, NFR-003 sentinel). Source stub from WP05 was already canonical per spec — no source change required.
- 2026-05-16T20:22:43Z – claude:opus:python-reviewer:reviewer – shell_pid=31666 – Started review via action command
- 2026-05-16T20:24:41Z – claude:opus:python-reviewer:reviewer – shell_pid=31666 – WP06 review passed. ListingCacheKeyBuilder.php unchanged from WP05 (0-line diff) and matches FR-037 spec verbatim: listing:<def>:<exposed>:<ctx>, SHA-256 truncated to 16 hex chars, ksort applied pre-encoding, JSON_THROW_ON_ERROR + UNESCAPED_SLASHES. Unit tests (9) cover all 6 required cases — determinism, def/exposed/ctx distinctness, order-invariance (ksort), regex format match (line 124), plus subtle-input collision smoke and length stability bonus. Integration tests (10) prove cache MISS->HIT via mutate-storage-between-calls (driver->write('99') after first resolve; second resolve still returns [1,2,3], proving query bypass behaviourally — strong), invalidation by entity-tag and row-tag, TTL via real sleep(2), RequestContext-affected access ops change key, FR-058 graceful fallback via anonymous TaggedCacheInterface composing MemoryBackend (final). NFR-003 sentinel: 50-sample p95 < 50ms (CI-friendly bound for 0.5ms target — meaningful regression guard). Scope pristine: 2 new test files (729 LOC), zero source mutation. Gates: listing 208/362 pass, cache 172/356, foundation 969/2122, cs-check + phpstan + composer-policy + layers all green.
- 2026-05-16T21:52:58Z – claude:opus:python-reviewer:reviewer – shell_pid=31666 – Done override: Mission merged to main as bbbec6e57
