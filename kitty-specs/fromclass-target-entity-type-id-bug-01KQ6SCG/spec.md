# Spec: fromClass field definitions missing targetEntityTypeId

**Mission slug:** `fromclass-target-entity-type-id-bug-01KQ6SCG`
**Mission type:** software-dev
**Target branch:** `main`
**Author:** Russell Jones
**Date:** 2026-04-27

## Problem

`EntityType::fromClass()` (`packages/entity/src/EntityType.php`) builds a field
map by delegating to `EntityMetadataReader::resolveFields()`, which constructs
`FieldDefinition` instances directly from `#[Field]` attributes. Those
constructions never populate `targetEntityTypeId`, so the resulting definitions
default to `''` (the constructor default).

Downstream, `EntityTypeManager::registerEntityType()` calls
`FieldDefinitionRegistry::registerCoreFields($entityTypeId, $fields)`, and the
registry rejects any `FieldDefinitionInterface` whose `getTargetEntityTypeId()`
does not match the entity type id being registered against
(`packages/field/src/FieldDefinitionRegistry.php` lines 39-46). Because every
field arrives with an empty target id, the strict equality check throws.

Reproducer (single line that triggers the bug end-to-end):

```php
$type = EntityType::fromClass(MyEntity::class);
$entityTypeManager->registerEntityType($type);  // throws inside FieldDefinitionRegistry
```

The current workaround in `packages/genealogy/tests/Unit/GenealogyFamilyServiceTest`
(and `GenealogyPedigreeServiceTest`) is to construct `EntityTypeManager` *without*
the optional 3rd `$fieldRegistry` argument, even though the registry is otherwise
wired through `SqlSchemaHandler`, `SqlEntityStorage`, and
`ContentEntityBase::setFieldRegistry()`. The `?->` short-circuit at
`EntityTypeManager::persistDefinition()` line 128 then silently no-ops the
strict check. That is a band-aid: the registry path is exactly what production
code exercises.

This is the conditional acceptance flagged by WP08's reviewer when M1
(`attribute-first-entity-definition-01KQ6DXE`, merged as `ce123bfe`) landed,
and is documented as the explicit TODO in `docs/specs/entity-system.md`
§"Known Transitional Gaps" item 4.

## Why this matters

- `fromClass()` is the canonical way to build content entity types now that M1
  has merged. Any caller that follows the documented flow hits this bug as
  soon as they wire the type through `EntityTypeManager` with a registry.
- The genealogy test workaround papers over the failure; new packages will
  encounter the same wall and either copy the workaround or rediscover the
  bug.
- The strict registry check is itself a correctness invariant we should keep
  — it caught a real misconfiguration. The fix must satisfy the invariant,
  not relax it.

## Goal

Make `EntityType::fromClass(X)` produce a value that
`EntityTypeManager::registerEntityType()` accepts against a real
`FieldDefinitionRegistry`, without weakening the registry's `targetEntityTypeId`
contract.

## Non-goals

- No changes to the `#[Field]` attribute surface or property declarations.
- No changes to how `FieldDefinitionRegistry` validates the target id (the
  strict check stays).
- No new `FieldDefinition` mutation API on the public surface beyond what the
  fix requires.
- No retrofit of existing manually-constructed `EntityType` callers — they
  already pass field arrays/definitions with the correct id where needed.

## Decision: Option A

Three options were considered:

- **Option A** — `EntityMetadataReader::resolveFields()` accepts the resolved
  type id and constructs `FieldDefinition` instances with `targetEntityTypeId`
  set. This is mechanical: `EntityMetadataReader::forClass()` already resolves
  `typeId` *before* it calls `resolveFields()`, so the value is already in
  scope; we just thread it through.
- **Option B** — `EntityType::fromClass()` post-processes the field map to set
  `targetEntityTypeId`. Requires either reflection-cloning a `final readonly`
  class or adding a `withTargetEntityTypeId()` immutable mutator. Bigger
  surface change for the same outcome.
- **Option C** — Loosen `registerCoreFields()` to accept empty
  `targetEntityTypeId` and fill it in. Discards a real invariant the registry
  uses to catch misconfiguration.

**We pick Option A.** It is the smallest change, keeps `FieldDefinition`
immutable with no new mutator, keeps the registry's invariant intact, and
puts the responsibility where it belongs — on the metadata reader that owns
field construction.

The public signature change is on `EntityMetadataReader::resolveFields()`,
which is `public static`. The known callers in the codebase are
`EntityType::fromClass()` (production) and the unit tests in
`packages/entity/tests/Unit/Attribute/EntityMetadataReaderTest.php`. Making
the new parameter optional with a `null` default keeps every existing caller
behavior-identical.

## Functional requirements

- **FR-1:** `EntityMetadataReader::resolveFields(string $class, ?string $entityTypeId = null)`
  threads `$entityTypeId` through to every `FieldDefinition` it constructs as
  the `targetEntityTypeId` constructor argument (using `$entityTypeId ?? ''`
  to keep the no-id call path identical to today).
- **FR-2:** `EntityMetadataReader::forClass()` resolves the type id first and
  passes it to `resolveFields()`; if the class lacks `#[ContentEntityType]`,
  `resolveFields()` is called with `null` and the resulting fields keep an
  empty `targetEntityTypeId` (existing behaviour for non-typed classes).
- **FR-3:** A regression test under `packages/entity/tests/Integration/`
  exercises the full pipeline end-to-end: build an `EntityType` via
  `fromClass()` and register it through `EntityTypeManager` with a real
  `FieldDefinitionRegistry`. The test fails on `main` today (target id
  empty → registry throws) and passes after the fix.
- **FR-4:** `packages/genealogy/tests/Unit/GenealogyFamilyServiceTest` and
  `GenealogyPedigreeServiceTest` are refactored to pass `$registry` as the
  3rd argument to `new EntityTypeManager(...)`. Workaround comments are
  removed.
- **FR-5:** `docs/specs/entity-system.md` §"Known Transitional Gaps" item 4
  is removed (or reworded to point at the resolved commit) since the gap is
  now closed.

## Acceptance criteria

- The new integration test passes.
- The existing genealogy tests pass with the registry wired into
  `EntityTypeManager`.
- `vendor/bin/phpunit packages/entity packages/field packages/genealogy`
  passes.
- No new public API surface on `FieldDefinition` (it stays `final readonly`
  with no mutators).
- Static analysis (`phpstan`/`psalm` if configured) clean for changed files.

## Risks and mitigations

- **Risk:** An unexamined caller of `EntityMetadataReader::resolveFields()`
  outside the audited set.
  **Mitigation:** Default the new parameter to `null` so existing callers
  continue to compile and behave identically; the constructor receives `''`
  via `?? ''`, matching today's defaulted value.
- **Risk:** Per-class metadata cache in `EntityMetadataReader` returning
  stale field definitions if any prior test built fields without the type id.
  **Mitigation:** Cache is keyed by class; field definitions are built fresh
  each cache miss and the type id never changes for a class. Test setup that
  needs isolation already calls `EntityMetadataReader::clearCache()`.

## Out-of-scope follow-ups

- Audit other places where `FieldDefinition` may be constructed without
  `targetEntityTypeId` to decide whether the constructor default of `''`
  should become a required argument. Tracked separately if surfaced.
