# Mission Specification: PHP 8.4 Lazy Object Hydration

**Mission**: php84-lazy-object-hydration-01KR82KZ
**Mission ID**: 01KR82KZYWGMJPTTD5X54JKHKK
**Mission type**: software-dev
**Target branch**: main
**Created**: 2026-05-10
**Status**: specify

## Summary

Adopt PHP 8.4 native lazy objects (`ReflectionClass::newLazyGhost()` and `ReflectionClass::newLazyProxy()`) at two structural sites in the Waaseyaa entity stack so that callers who touch only a subset of an entity's data avoid the cost of full hydration. The change must be transparent to all consumers — entity APIs, lifecycle hooks, access policies, identity comparisons, and event semantics behave identically to today.

This is the architectural follow-up to `php84-mechanical-modernization-01KR82KT` (which shipped trivial PHP 8.4 swaps). That mission deliberately deferred the structural sites to here.

## Background

The two sites:

1. **Entity hydration (`EntityInstantiator::fromStorage()` and `SqlEntityStorage::mapRowToEntity()`)** — currently materialize the entity object eagerly with all field values from the SQL row plus the `_data` JSON blob. Many call paths (list endpoints, GraphQL resolvers, access policies that key off `id`/`uuid`/`label` only) never read non-key fields, paying full hydration cost for nothing.
2. **Storage factory in `EntityTypeManager`** — the optional `?\Closure $storageFactory` is a hand-rolled lazy proxy: when a caller asks for storage of a given entity type, the closure is invoked to construct the `EntityStorageDriverInterface`. PHP 8.4's `newLazyProxy()` is the canonical replacement for this hand-rolled pattern.

## User Scenarios & Testing

### Primary scenarios

- **List endpoint efficiency**: A REST or GraphQL list response that returns 100 entities and reads only `id`, `uuid`, and `label` for each item must complete without populating non-key fields on any returned entity. Field reads beyond the entity-key set on a single item must transparently trigger initialization for that entity only.
- **Single-entity find then full read**: `EntityRepository::find($id)` followed by reading non-key fields must produce results identical to today (same field values, same lifecycle event ordering, same identity).
- **Access policy gating a list query**: A `FieldAccessPolicyInterface` implementation that inspects non-key entity state (e.g., reads `$entity->get('owner_id')` to compare to the current account) must continue to work correctly. Inspecting non-key state during policy evaluation triggers initialization for that entity — accepted as the cost of transparent semantics.
- **Storage acquisition deferral**: When a request boots the `EntityTypeManager` and asks for the storage of entity type A, the storage backing entity type B is not constructed unless and until type B is asked for.
- **Identity stability**: `$repo->find(1) === $repo->find(1)` must remain true within a unit of work. Lazy ghost equality must match eager-instance equality semantics in `===`, `instanceof`, and reflection-based code.

### Edge cases

- Entities created via `new User(['uid' => 2])` followed by `enforceIsNew()` and `save()` (the documented pre-set-ID path) must not become lazy ghosts — only entities materialized *from storage* are lazy.
- `EntityBase::preSave()` / `postSave()` / `preDelete()` / `postDelete()` lifecycle hooks must fire on the same instance, in the same order, with the same `$isNew` value as today.
- `saveMany()` / `deleteMany()` UnitOfWork batches must continue to dispatch buffered events post-commit even when the operands are lazy ghosts.
- `_data` JSON blob fields merged in by `mapRowToEntity()` must be available after initialization with the same semantics as eager hydration.
- Test code constructing entities with anonymous classes or via `InMemoryEntityStorage` (which does not go through `SqlEntityStorage`) must remain unaffected.
- `final class` entity subclasses must be lazy-able (PHP 8.4 supports lazy ghosts on final classes via reflection).

## Requirements

### Functional Requirements

| ID     | Requirement                                                                                                                                                                                                                | Status |
| ------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------ |
| FR-001 | `SqlEntityStorage` (or `EntityInstantiator`) must produce entity instances via `ReflectionClass::newLazyGhost()` when materializing from a fetched SQL row.                                                                | Open   |
| FR-002 | The lazy initializer must be a closure that captures the already-fetched row array (and the `_data` blob decoded once), so initialization does not re-query the database.                                                  | Open   |
| FR-003 | Entity-key fields (`id`, `uuid`, `label`, plus any keys declared in `EntityType::getKeys()`) must be populated eagerly on the lazy ghost, so reads of these fields skip initialization entirely.                          | Open   |
| FR-004 | Reading any non-key field, calling `set()`, calling `toArray()`, or invoking any method that introspects entity values must transparently trigger initialization — no caller code change required.                         | Open   |
| FR-005 | `EntityTypeManager` must replace the hand-rolled `?\Closure $storageFactory` with `ReflectionClass::newLazyProxy(EntityStorageDriverInterface::class, $factoryClosure)` per entity type. Construction is deferred to first method call. | Open   |
| FR-006 | `FieldAccessPolicyInterface` implementations that inspect non-key state during `fieldAccess()` must observe the same entity state as today (initialization triggers transparently when non-key state is read).             | Open   |
| FR-007 | `EntityBase` lifecycle hooks (`preSave`, `postSave`, `preDelete`, `postDelete`) and the corresponding `EntityRepository` events must fire on the same instance, in the same order, with the same `$isNew` semantics as eager hydration. | Open   |

### Non-Functional Requirements

| ID      | Requirement                                                                                                                                                                                              | Status |
| ------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------ |
| NFR-001 | Cold `find()` of a single entity that reads only entity-key fields must reduce wall-clock time by ≥30% versus the current eager path on a representative entity (≥5 non-key fields, including a `_data` blob value). | Open   |
| NFR-002 | List queries returning 100 entities where the consumer reads only entity-key fields must allocate ≥40% fewer initialized field objects than the current eager path (measured via a hydration counter in tests). | Open   |
| NFR-003 | Identity comparisons (`===`), `instanceof` checks, and reflection-based equality used by EntityRepository's identity map must produce results indistinguishable from eager hydration in all cases.       | Open   |
| NFR-004 | The change must not increase peak memory usage of the existing entity-storage test suite by more than 5% (closures retain row arrays until init, so a small increase is expected).                       | Open   |

### Constraints

| ID    | Constraint                                                                                                                                                                                                  | Status |
| ----- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------ |
| C-001 | No public API change to `EntityBase`, `ContentEntityBase`, `EntityRepository`, `EntityStorageDriverInterface`, or any registered entity subclass. Lazy ghosts must be observably indistinguishable from eager objects. | Open   |
| C-002 | All plumbing must live in `packages/entity/` and `packages/entity-storage/`. No upward layer imports. Run `bin/check-package-layers` after edits.                                                            | Open   |
| C-003 | Must work identically across all DBAL drivers in use today: SQLite, MySQL/MariaDB, PostgreSQL.                                                                                                              | Open   |
| C-004 | Must not regress the access pipeline: `AccessChecker`, `EntityAccessHandler`, `AccessPolicyInterface`, `FieldAccessPolicyInterface` must all continue to function with no contract change.                  | Open   |
| C-005 | Entities materialized via paths other than `SqlEntityStorage::mapRowToEntity()` (e.g., `new User([...])` + `enforceIsNew()`, `InMemoryEntityStorage`) must remain eager. Laziness is opt-in via the storage layer only. | Open   |

## Success Criteria

- A representative list-response benchmark (100 entities, key-fields-only consumption) shows the ≥30% wall-clock improvement of NFR-001 and the ≥40% allocation reduction of NFR-002.
- The full PHPUnit suite (`./vendor/bin/phpunit`) passes with the lazy implementation enabled, including the integration tests in `tests/Integration/PhaseN/` and the GraphQL fullstack tests in `tests/Integration/GraphQL/`.
- `bin/check-package-layers` reports no violations.
- A new contract test in `packages/entity-storage/tests/Contract/` asserts that a lazy ghost and an eager instance materialized from the same row produce identical results for: every public `EntityBase` method, JSON serialization, lifecycle event sequence, and identity comparisons.
- A new test in `packages/entity/tests/Unit/` asserts that `EntityTypeManager` does not invoke a registered storage factory until that storage is requested by `getStorage($entityTypeId)`.

## Key Entities

This mission does not introduce new entity types. It changes how existing entities are instantiated. Affected types include all classes registered via `EntityTypeManager` — at minimum: `User`, `Node`, `Term`, `Media`, `Path`, `Menu`, `Note`, plus any entity subclass declared by application packages.

## Assumptions

- PHP 8.4's lazy object reflection API (`newLazyGhost`, `newLazyProxy`) is stable and available in all targeted environments — already required by the project (`composer.json` requires `php >=8.4`).
- The existing reflection-based constructor-shape detection in `SqlEntityStorage` (which distinguishes entity subclasses with `(array $values)` constructors from other shapes) is compatible with `newLazyGhost` — to be verified in plan/research.
- `final class` is not a barrier to laziness; PHP 8.4 supports lazy ghosts on final classes.
- The closure-capturing-row strategy's memory overhead is acceptable in the working set of typical request lifetimes (single request rarely materializes >10k entity rows). Validated by NFR-004.
- `FieldAccessPolicyInterface` policies that inspect non-key state are uncommon; the small lazy-benefit loss for that case is acceptable in exchange for transparent semantics.

## Out of Scope

- Lazy hydration for non-SQL storage drivers (e.g., `InMemoryEntityStorage`).
- Lazy proxies for any service other than `EntityTypeManager`'s storage factory (no broad rollout to plugin services, http kernel components, etc.).
- New public API for callers to opt out of laziness (transparent only — there is no toggle).
- Caching changes; the `_data` JSON merge semantics are preserved as-is.
- Documentation rewrites of `docs/specs/entity-system.md` beyond noting the laziness mechanic. The spec stays current via the existing drift-detector flow.

## Open Questions

All three open questions identified during specify have been resolved:

1. **Initializer state**: closure captures the fetched row (no re-query on materialization). Memory cost preferred over I/O surprise.
2. **Ghost vs proxy for `EntityTypeManager` storage**: `newLazyProxy(EntityStorageDriverInterface::class, …)` — interface-typed, canonical PHP 8.4 use case.
3. **`FieldAccessPolicyInterface` interaction**: policies that read non-key state trigger initialization. Transparent semantics; lazy benefit lost only on entries a policy inspects beyond entity keys.

Plan-phase research must still benchmark NFR-001 / NFR-002 thresholds and verify that `final class` entity subclasses + the reflection-based constructor-shape detection in `SqlEntityStorage` are compatible with `newLazyGhost`.

## References

- Companion mission: `php84-mechanical-modernization-01KR82KT` (commit `4372373`, merged 2026-05-10).
- Architectural context: `CLAUDE.md` — orchestration row for `packages/entity/*`, `packages/entity-storage/*` → `docs/specs/entity-system.md`.
- Memory entry: `feedback_php84_deprecated_attribute_targets.md` — PHP 8.4 attribute-target restrictions (relevant if any new attributes are introduced).
- PHP 8.4 lazy objects: <https://www.php.net/manual/en/language.oop5.lazy-objects.php>.
