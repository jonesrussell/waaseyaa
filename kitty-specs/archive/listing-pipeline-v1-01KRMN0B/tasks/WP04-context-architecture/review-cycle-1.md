# WP04 Review — Cycle 1

**Reviewer:** opus-reviewer
**Commit reviewed:** `b6ccb157a`
**Verdict:** Rejected — one structural blocker; everything else is solid

## Blockers (must fix)

### B1. `RequestContext` belongs in `packages/foundation/`, not `packages/cache/`

**Where:** `packages/cache/src/RequestContext.php` (new file, not in WP04 `owned_files`); imported by `packages/cache/src/ContextResolver.php` lines 71, 95, 111, 122 as the `RequestContext` parameter type.

**Why this is a blocker:**

1. The WP04 task spec and `contracts/context-architecture.md` both treat `RequestContext` as a *pre-existing prerequisite* sourced from foundation — the risk register explicitly anticipated this case and says *"surface as a blocker before coding"* if foundation lacks the accessors. That instruction was bypassed.
2. The class’s own docblock declares the intended FQCN to be `Waaseyaa\Foundation\Http\RequestContext`. Shipping it under `Waaseyaa\Cache\RequestContext` and then promoting it later is a **breaking import change** for every downstream consumer — `ListingResolver` (WP05), `ListingCacheKeyBuilder` (WP06), and any extension package implementing `cacheContexts()` resolution. We pay the cost across N packages instead of fixing it in one place now.
3. Layer-wise this is a same-layer move (Layer 0 → Layer 0); both packages are already siblings. The architectural risk of doing it now is small; the risk of deferring it is real.
4. `owned_files` for WP04 lists exactly 5 files (3 src + 2 tests). `RequestContext.php` and the `composer.json` edge addition are scope-creep that bypasses the manifest gate the orchestrator depends on.

**How to apply (Option B):**

1. **Move** `packages/cache/src/RequestContext.php` → `packages/foundation/src/Http/RequestContext.php`. Update the namespace to `Waaseyaa\Foundation\Http`. Keep `final readonly`, keep the 5 accessors verbatim, keep the `@api` docblock (drop the "promote later" note since this *is* the final home).
2. **Update imports** in `packages/cache/src/ContextResolver.php` line 7-area: replace any `use` of the cache-local class with `use Waaseyaa\Foundation\Http\RequestContext;`. Method signatures on lines 71, 95, 111, 122 stay textually identical (just bind to the new FQCN).
3. **Leave the existing `waaseyaa/foundation` dependency** in `packages/cache/composer.json` — it’s now justified for both `LoggerInterface`/`NullLogger` AND `Http\RequestContext`. Version literal `^0.1.0-alpha.179` is correct against current tag (`v0.1.0-alpha.179`).
4. **Move/duplicate the test coverage** for the value object itself into `packages/foundation/tests/Unit/Http/RequestContextTest.php` (small — `__construct`/accessors round-trip). The existing `ContextResolverTest` keeps its current shape and just binds to the foundation FQCN.
5. **Update the WP04 task spec’s `owned_files`** (in `kitty-specs/listing-pipeline-v1-01KRMN0B/tasks/WP04-context-architecture/...`) to include the foundation-side files. This keeps the orchestrator’s scope check honest.

**Out of scope for this cycle (do NOT do):**
- Do not refactor to an interface-based contract (Option C). Concrete `final readonly` value object is the right shape — interface deferral would over-engineer this and the contract explicitly types it as a class.
- Do not edit any other foundation surface. Just add `Http/RequestContext.php`.

## Suggestions (non-blocking — apply during the same cycle)

- **S1.** Consider adding a `RequestContextTest` fixture/factory helper in `packages/foundation/testing/` so WP05/WP06 tests can build canonical instances without re-constructing positional args. Not required for approval — call it out if you don’t do it so a follow-up WP can.
- **S2.** The `@throws \InvalidArgumentException` block in `ContextRegistry::register()` is well-documented; consider adding a one-line `@example` showing a rejected name for symmetry with the accepted-format docblock above.

## Approved aspects (do not change)

- **FR-035 (ContextRegistry whitelist):** ✓
  - 5 canonical names seeded — `packages/cache/src/ContextNames.php:33,35,37,39,44`
  - Regex `^[a-z][a-z0-9_.]*$` enforced — `packages/cache/src/ContextRegistry.php:75-89` (register validates and throws `\InvalidArgumentException`)
  - Idempotent re-registration — `ContextRegistry.php:69` (docblock + behavior)
  - `has()` direct match + `url.query.` prefix match — `ContextRegistry.php:98-110` (line 107: `str_starts_with($name, ContextNames::URL_QUERY_PREFIX)`)
  - `all()` returns sorted list — `ContextRegistry.php:122-126` (`sort($names, SORT_STRING)`)

- **FR-036 (ContextResolver deterministic resolution):** ✓
  - Match-table dispatch — `packages/cache/src/ContextResolver.php:82` (`match (true)`)
  - `user.roles` sorts in-resolver — `ContextResolver.php:103` (`sort($roles, SORT_STRING)`), joined with `,` on line 105 — sort-stability invariant pinned
  - `url.query.<param>` prefix dispatch — `ContextResolver.php:122` (`resolveUrlQuery()`)
  - Unknown-name warn-and-bypass — `ContextResolver.php:74` (`$this->logger->warning(...)`) returns empty string per spec, does not throw
  - `LoggerInterface`/`NullLogger` are foundation-sourced — `ContextResolver.php:7-8`

- **ContextNames constants:** all 5 present with correct literal values matching the contract verbatim. `public const string` typed-constants (PHP 8.3+) — nice touch.

- **Tests:** 28 new tests, cache suite 172/172 pass, listing 135/135 pass (no regression).

- **Gates:** `composer cs-check` clean; `composer phpstan` clean (level 5, 1330 files); `bin/check-composer-policy` OK; `bin/check-package-layers` OK.

- **`composer.json` version literal:** `^0.1.0-alpha.179` matches `git describe --tags` (`v0.1.0-alpha.179`) — CP-NEW satisfied.

## Orchestrator note (not WP04’s concern)

- `move-task` required `--force` due to an untracked WP12 review-cycle file. Flagged for orchestrator cleanup, not blocking this review.
- `spec-kitty agent tasks mark-status T016-T020` failed (pre-existing tasks.md format issue, ongoing for all WPs).

---

**Re-review scope:** After remediation, re-review must confirm (a) `RequestContext.php` lives in foundation under `Waaseyaa\Foundation\Http`, (b) `ContextResolver` imports it via that FQCN, (c) `owned_files` updated, (d) all gates still green, (e) no other foundation surface touched.
