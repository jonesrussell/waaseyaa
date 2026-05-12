# Implementation Plan: PHP 8.4 Lazy Object Hydration

**Branch**: `main` (planning + final merge target) | **Date**: 2026-05-10 | **Spec**: [spec.md](spec.md)
**Mission**: php84-lazy-object-hydration-01KR82KZ · **Mission ID**: 01KR82KZYWGMJPTTD5X54JKHKK
**Mission type**: software-dev

## Summary

Migrate two structural sites in the Waaseyaa entity stack to PHP 8.4 native lazy objects:

1. **Entity hydration** — `packages/entity-storage/src/Hydration/EntityInstantiator.php` and `packages/entity-storage/src/SqlEntityStorage.php` produce entity instances via `ReflectionClass::newLazyGhost()`. Initializer is a closure capturing the fetched row + decoded `_data` blob (no re-query). Entity-key fields populated eagerly so cheap `id`/`uuid`/`label` reads bypass initialization.
2. **Storage factory** — `packages/entity/src/EntityTypeManager.php` replaces the hand-rolled `?\Closure $storageFactory` with `ReflectionClass::newLazyProxy(EntityStorageDriverInterface::class, $factoryClosure)` per entity type.

Both changes are transparent to consumers: no public API change, no new opt-in, identity comparisons stable, lifecycle hooks fire in the same order on the same instance.

## Branch Contract

- **Current branch**: `main`
- **Planning / base branch**: `main`
- **Final merge target**: `main`
- **branch_matches_target**: ✅

All planning artifacts commit to `main`. Implementation worktrees materialize later via `spec-kitty next` after `/spec-kitty.tasks`.

## Technical Context

| Aspect | Resolution |
| --- | --- |
| Language | PHP 8.4+ (`declare(strict_types=1)` everywhere; required by composer.json) |
| Reflection API | `ReflectionClass::newLazyGhost()` for entity instances; `ReflectionClass::newLazyProxy()` for storage factory |
| Initializer state | Closure capturing the fetched row array + once-decoded `_data` blob (no re-query on materialization) |
| Storage proxy mode | `newLazyProxy(EntityStorageDriverInterface::class, …)` — interface-typed, canonical PHP 8.4 use case |
| Field-policy semantics | Policies that read non-key state trigger initialization — transparent semantics, accepted lazy-benefit loss for that case |
| Affected packages | `packages/entity/` (L1 Core Data), `packages/entity-storage/` (L1 Core Data) |
| Layer discipline | All edits remain in the same layer; `bin/check-package-layers` must report no new violations |
| Test infra | PHPUnit 10.5; new contract test for ghost-vs-eager parity; new benchmark group `#[Group('benchmark')]` in `packages/entity-storage/tests/Benchmark/` |
| DBAL drivers | SQLite (default), MySQL/MariaDB, PostgreSQL — verified via existing integration tests |
| Out-of-scope storage | `InMemoryEntityStorage` (test fixture) remains eager — no change |

No `[NEEDS CLARIFICATION]` markers. The three architectural decisions from spec discovery are locked.

## Charter Check

Charter context loaded from `spec-kitty charter context --action plan --json` → `mode: compact`.

- Template set: `software-dev-default` · Paradigms: `domain-driven-design` · Tools: `git`, `spec-kitty`
- Languages: `php`, `typescript` (this mission is PHP-only)
- Active directives: `DIR-001`, `DIR-002`, `DIR-003`. None of these conflict with the lazy-object adoption — laziness is an internal implementation refinement and changes no public contract or domain model.
- No tactics registered.

**Pre-design gate**: PASS. **Post-design gate** (re-evaluated after Phase 1 below): PASS — the design preserves all existing entity-system invariants; transparent semantics mean no new public surface to govern.

## Phase 0 — Research (`research.md`)

Three research items, scoped tight:

1. **Compatibility of `newLazyGhost()` with the existing `SqlEntityStorage` reflection-based constructor-shape detection.** Today, `SqlEntityStorage` uses reflection to detect entities whose constructor accepts only `(array $values)` (the canonical entity-subclass shape: hardcoded `entityTypeId`, hardcoded `entityKeys`). Verify that `newLazyGhost()` works when the class has such a constructor — `newLazyGhost()` does not invoke the constructor until the lazy ghost is initialized; the initializer closure is what populates field state. Confirm that `final class` does not block laziness (PHP 8.4 supports lazy ghosts on final classes).
2. **Audit of `FieldAccessPolicyInterface` implementations that inspect non-key entity state.** Grep `packages/*/src/**/*AccessPolicy*.php` for calls to `$entity->get(...)` and direct property reads. Catalogue which field policies will trigger ghost initialization on each entry they evaluate. This is informational — semantics are accepted — but the count informs whether NFR-002's ≥40% allocation reduction holds for typical list-with-policy workloads.
3. **Benchmark methodology.** A bespoke PHPUnit-based harness inside `packages/entity-storage/tests/Benchmark/`, marked `#[Group('benchmark')]` and excluded from the default test run. Each benchmark seeds an in-memory SQLite DB with a representative entity (≥5 non-key fields including a `_data` JSON value), then measures three scenarios with `microtime(true)` and a hydration counter:
   - **Cold find, key-only read** → expects ≥30% wall-clock improvement (NFR-001)
   - **List-100, key-only read** → expects ≥40% reduction in initialized field allocations (NFR-002)
   - **Cold find, full read** → must show no statistically meaningful regression
   The hydration counter is a static counter incremented inside the lazy initializer closure (test-only mechanism). Run with `./vendor/bin/phpunit --group=benchmark`. CI does not run this group; it is invoked manually for verification.

**Output**: `research.md` (this file's neighbor).

## Phase 1 — Design (`data-model.md`, `contracts/`, `quickstart.md`)

### Data model

No new entities. Affected types are all existing classes registered in `EntityTypeManager`. The lazy ghost wraps the same instance type the eager path produces today; reflection identity (`new ReflectionObject($entity)->getName()`) returns the same FQCN.

The only "model" change is internal: `EntityValues` (the field-bag inside `EntityBase`) is not populated until the lazy ghost initializer fires — except for entity-key fields, which are written into the bag eagerly during `newLazyGhost()` setup so key reads are free.

### Contracts

No external HTTP / GraphQL / CLI contract changes. Internal contract surfaces touched:

- `packages/entity-storage/src/Hydration/EntityInstantiator.php` — `fromStorage(EntityTypeInterface $type, array $row): EntityInterface` keeps its signature; the returned object is now a lazy ghost.
- `packages/entity-storage/src/SqlEntityStorage.php` — `mapRowToEntity(array $row): EntityInterface` keeps its signature; delegates to `EntityInstantiator` (factor through if not already).
- `packages/entity/src/EntityTypeManager.php` — `getStorage(string $entityTypeId): EntityStorageDriverInterface` keeps its signature; the returned object is now a lazy proxy until first method call.

### Contract test (new)

`packages/entity-storage/tests/Contract/LazyHydrationParityContractTest.php` — for each canonical entity (User, Node, Term, plus an anonymous `EntityBase` subclass fixture), assert that a lazy ghost and an eager instance materialized from the same row are equivalent across:

- All `EntityBase` public-method outputs (`id()`, `uuid()`, `label()`, `bundle()`, `getEntityTypeId()`, `getKeys()`, `toArray()`, `isNew()`)
- `JsonSerializable::jsonSerialize()` output
- Lifecycle event sequence under save/delete (preSave + PRE_SAVE event + persist + POST_SAVE event + postSave; same for delete)
- Identity (`$repo->find(1) === $repo->find(1)` within a unit of work)
- `instanceof` and reflection class-name resolution

A second contract test `packages/entity/tests/Unit/EntityTypeManagerLazyStorageTest.php` asserts that registering N entity types and asking for storage of only one does not invoke the other N-1 storage factories.

### Quickstart (`quickstart.md`)

Step-by-step for a maintainer verifying laziness end-to-end:

1. Run the existing test suite — `./vendor/bin/phpunit` — must pass with no regressions.
2. Run the new contract tests in isolation — `./vendor/bin/phpunit packages/entity-storage/tests/Contract/LazyHydrationParityContractTest.php` and `./vendor/bin/phpunit packages/entity/tests/Unit/EntityTypeManagerLazyStorageTest.php`.
3. Run the benchmark group — `./vendor/bin/phpunit --group=benchmark` — and verify NFR-001 (≥30%) and NFR-002 (≥40%) thresholds are met.
4. Run `bin/check-package-layers` — must report no violations.
5. Hand-test in `composer dev` — boot the dev server, hit a list endpoint, exercise an entity-detail endpoint, confirm no behavior change visible to consumers.

## Out of Scope (re-stated for plan reviewers)

- `InMemoryEntityStorage` and other non-SQL storage drivers.
- Any service other than `EntityTypeManager`'s storage factory becoming lazy.
- Caller-facing opt-out (no toggle — laziness is transparent).
- `_data` JSON merge semantics (preserved as-is).
- Spec docs rewrite of `docs/specs/entity-system.md` beyond a one-paragraph note.

## Risk Register

| Risk | Likelihood | Mitigation |
| --- | --- | --- |
| `newLazyGhost()` incompatible with the `(array $values)` constructor pattern hardcoding `entityTypeId`/`entityKeys` | Low | Phase 0 research item #1 verifies this on a fixture before touching production code. If incompatible, fall back to a small `EntityFactory::createGhost()` helper that bypasses the constructor and writes via reflection. |
| Identity map breaks because `===` compares lazy ghosts and eager instances differently | Low | PHP 8.4 lazy ghosts preserve object identity by design; covered by `LazyHydrationParityContractTest`. |
| Memory regression > NFR-004's 5% ceiling because closures retain row arrays | Medium | Benchmark explicitly measures peak memory. If exceeded, an alternative initializer that captures only the row reference (not a copy) is the first fallback. |
| Field policy that reads non-key state silently triggers init for every entity in a list, erasing NFR-002 win | Medium | Phase 0 research item #2 enumerates affected policies. If any high-frequency policy triggers init on every entry, raise a follow-up issue (do not block this mission). |
| Lifecycle event ordering changes because the lazy ghost initializer fires inside a `preSave` invocation | Low | Contract test asserts exact event sequence; fix-forward if a regression is found during implementation. |

## Acceptance Gates (mission-level)

- All FRs/NFRs/Constraints in `spec.md` have a corresponding test or check.
- `bin/check-package-layers` passes.
- `bin/check-composer-policy` passes (no manifest churn expected).
- `./vendor/bin/phpunit` passes.
- `./vendor/bin/phpunit --group=benchmark` meets NFR thresholds.
- `composer phpstan` passes (level 5).
- `composer cs-check` passes.

## Branch Contract — final restatement

- **Current branch**: `main`
- **Planning / base branch**: `main`
- **Final merge target**: `main`
- **branch_matches_target**: ✅

Mission proceeds on `main`. Next: `/spec-kitty.tasks --mission php84-lazy-object-hydration-01KR82KZ`.

## Generated Artifacts

- [research.md](research.md) — Phase 0 research findings
- [data-model.md](data-model.md) — internal data-flow notes (no new entities)
- [contracts/lazy-hydration-parity.md](contracts/lazy-hydration-parity.md) — internal contract under test
- [quickstart.md](quickstart.md) — maintainer verification steps
