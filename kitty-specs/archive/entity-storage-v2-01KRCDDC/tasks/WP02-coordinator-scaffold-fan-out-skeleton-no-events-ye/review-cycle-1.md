# WP02 — Cycle 1 Review

**Verdict:** APPROVED

**Reviewer:** opus (lane-a)
**Commit reviewed:** `e24b52ea5` — feat(WP02): coordinator scaffold + fan-out skeleton (T008–T012)
**Base:** `kitty/mission-entity-storage-v2-01KRCDDC`
**Lane:** `kitty/mission-entity-storage-v2-01KRCDDC-lane-a`

## Summary

WP02 delivers a clean, forward-compatible coordinator scaffold. Field-level
fan-out is correctly implemented with primary-first ordering, three-tier
resolution precedence, and a hard failure on unregistered backend ids. The
canonical `Entity → Repository → Coordinator → Driver → DBAL` pipeline is
preserved; the repository retains ownership of hydration, events, and language
fallback. WP04's dispatcher slot and WP07's `getPrimaryStorageBackend()` are
both reserved without forcing constructor churn later.

## Acceptance criteria — verified

| # | Criterion | Status |
|---|-----------|--------|
| 1 | FR-017–FR-021 coverage (read/write/delete fan-out, primary-first, registration-order alternates, resolution precedence) | OK |
| 2 | `EntityStorageCoordinator` ctor accepts `?EventDispatcherInterface $dispatcher = null` (WP04 slot, no events dispatched) | OK |
| 3 | `BackendResolver` precedence: `FieldDefinition::getBackendId()` → `getPrimaryStorageBackend()` (reflection-guarded for WP07) → `sql-blob` | OK |
| 4 | `new BackendRegistrar(` appears only in production `BackendRegistrarFactory`; tests construct it directly (allowed for fixtures) | OK |
| 5 | `EntityQuery` imported but never invoked — opaque marker preserved for WP06 | OK |
| 6 | Framework provider classification via `IsFrameworkBackendProviderInterface` (instanceof / interface implements), not class-name string match | OK |
| 7 | Canonical pipeline preserved; coordinator does not subsume repository; `EntityRepository` only adds nullable last-position param + accessor | OK |
| 8 | Namespace `Waaseyaa\EntityStorage` in all new/modified files | OK |
| 9 | Layer rule (L1) — no upward imports; `bin/check-package-layers` clean | OK |
| 10 | `@api` on every new public symbol (`EntityStorageCoordinator`, `BackendResolver`, `UnknownBackendException`, `getCoordinator()` on Factory & Repository) | OK |
| 11 | No `psr/log`, no `Illuminate\*`, no service locators (verified via grep) | OK |
| 12 | `declare(strict_types=1)` + `final class` everywhere | OK |
| 13 | Tests: RoutingTest exercises three-field fixture (backend-a override / backend-b override / sql-blob default), spy backends record invocations, primary-first ordering asserted via global-order array; PipelineInvariantTest uses reflection to verify no PDO reference and DBAL routing | OK |
| 14 | Non-goals untouched: no moderation, no revisions, no per-field translation, no vector/remote backends, no migration UI | OK |

## Gates (re-run from lane worktree)

| Gate | Result |
|------|--------|
| `composer cs-check` | clean — 0 files needed fixes |
| `composer phpstan` | OK — 0 errors across 1208 files |
| `bin/check-package-layers` | OK — layer constraints satisfied |
| `./vendor/bin/phpunit packages/entity-storage/tests/Integration/Coordinator/` | 12/12 pass (30 assertions); 2 PHP deprecations (see below) |

## Non-blocking observations (carry forward to WP03+)

1. **PHP 8.5 deprecation in PipelineInvariantTest** — `ReflectionProperty::setAccessible()`
   is called at `PipelineInvariantTest.php:221` and `:245`. Since PHP 8.1 the
   method is a no-op; PHP 8.5 emits a deprecation. The calls can simply be
   deleted (PHP 8.5 grants access automatically). Worth cleaning up in WP03 or
   a follow-up since the constitution lists PHP 8.5+ as the baseline. Tests
   still pass and assertions are unchanged.
2. **WP07 alignment** — `BackendResolver::resolveId()` and
   `EntityStorageCoordinator::resolvePrimaryBackendId()` both use `method_exists`
   + `ReflectionMethod::invoke` to call `getPrimaryStorageBackend()` until WP07
   adds it to `EntityTypeInterface` (or to `EntityType` directly). When WP07
   lands, verify the method name, return type (`?string`), and whether the
   interface gets the method or only the concrete class — then collapse the
   reflection guard to a direct call.
3. **Coordinator delete semantics** — `delete()` iterates `array_keys($groups)`
   so primary-first ordering is not enforced on delete. The spec only requires
   primary-first on write (§6.2). Confirm in WP04 whether lifecycle events for
   delete need ordering guarantees; if so, mirror the write-side primary-first
   loop.
4. **`@phpstan-ignore property.onlyWritten` on the dispatcher slot** is the
   right marker for now; WP04 will read the property and the suppression can
   be removed.

## Files verified

- `packages/entity-storage/src/EntityStorageCoordinator.php` (new, 195 lines)
- `packages/entity-storage/src/BackendResolver.php` (new, 91 lines)
- `packages/entity-storage/src/Exception/UnknownBackendException.php` (new, 29 lines)
- `packages/entity-storage/src/EntityStorageFactory.php` (modified, +43 lines)
- `packages/entity-storage/src/EntityRepository.php` (modified, +16 lines)
- `packages/entity-storage/tests/Integration/Coordinator/RoutingTest.php` (new, 393 lines)
- `packages/entity-storage/tests/Integration/Coordinator/PipelineInvariantTest.php` (new, 311 lines)

## Verdict rationale

The diff is faithful to spec §3.4 + §6, data-model §4, and research §2.
Forward-compat slots for WP04 (dispatcher) and WP07 (primary-backend lookup)
are correctly reserved. All gates clean. Dependent WPs (WP03, WP04, WP05) can
proceed against this scaffold.
