---
work_package_id: WP02
title: HasListingsInterface + ListingDiscoverer + ListingDefinitionRegistry
dependencies:
- WP01
requirement_refs:
- FR-015
- FR-016
- FR-017
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T007
- T008
- T009
- T010
history: []
authoritative_surface: packages/listing/
execution_mode: code_change
owned_files:
- packages/listing/src/HasListingsInterface.php
- packages/listing/src/ListingDiscoverer.php
- packages/listing/src/ListingDefinitionRegistry.php
- packages/listing/tests/Unit/Discovery/**
tags: []
agent: "claude:sonnet:python-implementer:implementer"
shell_pid: "11757"
---

## Objective

Build the discovery + registry surface that connects `ListingDefinition` instances (declared by app/package `ServiceProvider`s) to the `ListingResolver` (consumed in WP05). Mirrors the `HasNativeCommandsInterface` / `HasMigrationsInterface` capability-interface pattern from M-002.

## Context

- `HasListingsInterface` is the provider capability — any `ServiceProvider` (or equivalent) declares it to expose listings.
- `ListingDiscoverer` scans known providers (via reflection on the manifest's registered service providers) and flattens their `listings()` returns.
- `ListingDefinitionRegistry` is the post-discovery lookup surface — id-keyed, throws `UnknownListingException` on miss.
- All three types are stable surface (charter §5.X).

## Subtask details

### T007 — `HasListingsInterface` contract

**Steps:**
1. Create `packages/listing/src/HasListingsInterface.php`:
   ```php
   namespace Waaseyaa\Listing;
   interface HasListingsInterface
   {
       /** @return list<ListingDefinition> */
       public function listings(): array;
   }
   ```

**Files:** `packages/listing/src/HasListingsInterface.php` (new, ~15 lines).

### T008 — `ListingDiscoverer` service

**Purpose:** Scan known service providers, find `HasListingsInterface` implementors, collect their listings.

**Steps:**
1. Create `packages/listing/src/ListingDiscoverer.php`:
   - `final class ListingDiscoverer`
   - Constructor: injects the framework's service-provider registry (look at how `HasNativeCommandsInterface` discovery works in packages/cli — mirror that)
   - Method `public function discover(): array` returns `list<ListingDefinition>`:
     1. Iterate registered service providers
     2. For each provider that `instanceof HasListingsInterface`, call `->listings()`
     3. Flatten into a single list
     4. Detect duplicate `id` values across providers — throw `\LogicException` with both provider class names + the conflicting id
2. Cache the discover result: results should be deterministic given the same set of providers; future `PackageManifestCompiler` integration (WP11) will memoize into `var/manifest.php`.

**Files:** `packages/listing/src/ListingDiscoverer.php` (new, ~60 lines).

**Validation:** Two fixture providers each declaring one listing produce a 2-element discovery result. Duplicate id raises `\LogicException` with diagnostic message.

### T009 — `ListingDefinitionRegistry`

**Steps:**
1. Create `packages/listing/src/ListingDefinitionRegistry.php`:
   ```php
   namespace Waaseyaa\Listing;
   use Waaseyaa\Listing\Exception\UnknownListingException;

   final class ListingDefinitionRegistry
   {
       /** @param array<non-empty-string, ListingDefinition> $byId */
       public function __construct(private readonly array $byId) {}

       public function get(string $id): ListingDefinition
       {
           return $this->byId[$id] ?? throw new UnknownListingException($id);
       }

       public function has(string $id): bool { return isset($this->byId[$id]); }

       /** @return array<non-empty-string, ListingDefinition> */
       public function all(): array { return $this->byId; }
   }
   ```

**Files:** `packages/listing/src/ListingDefinitionRegistry.php` (new, ~30 lines).

### T010 — Discovery + registry unit tests

**Steps:**
1. Create `packages/listing/tests/Unit/Discovery/ListingDiscovererTest.php`:
   - Test: empty provider set → empty discovery
   - Test: single provider with one listing → 1-element result
   - Test: two providers with one listing each → 2-element result
   - Test: duplicate id across two providers → throws `\LogicException`
   - Test: provider not implementing `HasListingsInterface` is ignored
2. Create `packages/listing/tests/Unit/Discovery/ListingDefinitionRegistryTest.php`:
   - Test: `get()` returns registered definition
   - Test: `get()` throws `UnknownListingException` on miss; exception carries the listing id
   - Test: `has()` returns true/false correctly
   - Test: `all()` returns the full id-keyed map
3. Use anonymous test fixture classes implementing `HasListingsInterface` to keep tests self-contained.

**Files:** Tests under `packages/listing/tests/Unit/Discovery/` (new, ~200 lines total).

## Test strategy

Unit tests only at this layer. Integration coverage that exercises real `ServiceProvider` discovery happens in WP11.

## Definition of Done

- [ ] All 3 source files exist with `data-model.md`-matching signatures
- [ ] Unit tests cover all positive + negative cases above
- [ ] `composer cs-check` + `composer phpstan` green
- [ ] `vendor/bin/phpunit packages/listing/tests/Unit/Discovery/` green

## Risks

| Risk | Mitigation |
|---|---|
| Duplicate listing id across providers silently loses one | Discoverer explicitly throws on duplicate; test pins the behavior |
| Discoverer relies on a specific service-provider registry shape that may evolve | Use the same accessor pattern as `HasNativeCommandsInterface` discovery (read its source for the canonical path) |
| Listing definitions captured at boot time may go stale if app re-registers providers at runtime | v0.x assumes boot-time registration; runtime re-registration is out of scope |

## Reviewer guidance

- Verify discovery follows the same shape as existing `HasNativeCommandsInterface` discovery (read `packages/cli/src/...` for the canonical pattern).
- Verify duplicate-id detection is a hard fail (LogicException), not a silent collision.
- Verify the registry's `UnknownListingException` carries the requested id.

## Implementation command

```bash
spec-kitty agent action implement WP02 --agent <name>
```

## Activity Log

- 2026-05-16T19:04:31Z – claude:sonnet:python-implementer:implementer – shell_pid=11757 – Started implementation via action command
- 2026-05-16T19:11:13Z – claude:sonnet:python-implementer:implementer – shell_pid=11757 – WP02 ready: discovery + registry surface. Tests, cs-check, phpstan, composer policy, layer check all clean.
