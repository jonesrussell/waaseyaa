# Implementation Plan: fromClass field definitions missing targetEntityTypeId

**Mission slug:** `fromclass-target-entity-type-id-bug-01KQ6SCG`
**Branch:** `main` (lane branch will be created by spec-kitty during implement)
**Date:** 2026-04-27
**Spec:** [spec.md](spec.md)

## Approach summary

Thread the resolved entity type id from `EntityMetadataReader::forClass()` into
`EntityMetadataReader::resolveFields()` so every `FieldDefinition` the reader
constructs carries the correct `targetEntityTypeId`. The id is already
resolved at the top of `forClass()` before `resolveFields()` is called — the
fix is mechanical signature plumbing, not a redesign.

## Bug confirmation (so the implementer doesn't re-derive it)

End-to-end failure path on `main`:

1. `EntityMetadataReader::resolveFields()` ([packages/entity/src/Attribute/EntityMetadataReader.php:67](../../packages/entity/src/Attribute/EntityMetadataReader.php))
   constructs `FieldDefinition` instances at lines 104-116 without
   `targetEntityTypeId`, so it defaults to `''`.
2. `EntityType::fromClass()` ([packages/entity/src/EntityType.php:126](../../packages/entity/src/EntityType.php))
   stuffs that field map into `_fieldDefinitions`.
3. `EntityType::getFieldDefinitions()` ([packages/entity/src/EntityType.php:215](../../packages/entity/src/EntityType.php))
   passes `FieldDefinitionInterface` instances through unchanged via the
   `instanceof` short-circuit — the `targetEntityTypeId: $this->id` patch on
   line 242 only fires for the array-meta path, not the object path.
4. `EntityTypeManager::persistDefinition()` ([packages/entity/src/EntityTypeManager.php:128](../../packages/entity/src/EntityTypeManager.php))
   calls `$this->fieldRegistry?->registerCoreFields($type->id(), $type->getFieldDefinitions())`.
5. `FieldDefinitionRegistry::registerCoreFields()` ([packages/field/src/FieldDefinitionRegistry.php:39](../../packages/field/src/FieldDefinitionRegistry.php))
   throws because `'' !== $entityTypeId`.

The genealogy workaround (`packages/genealogy/tests/Unit/GenealogyFamilyServiceTest.php:37-44`
and `GenealogyPedigreeServiceTest.php`): construct `EntityTypeManager` with
only `$dispatcher` and the storage factory — omit the optional 3rd
`$fieldRegistry` constructor argument. The `?->` on line 128 then silently
no-ops, hiding the failure. The registry IS constructed and wired into
`SqlSchemaHandler`, `SqlEntityStorage`, and `ContentEntityBase::setFieldRegistry()`
— it's only the `EntityTypeManager` link that's missing.

## Caller audit for `EntityMetadataReader::resolveFields()`

- `EntityType::fromClass()` — production caller (sole caller in `src/`).
- `packages/entity/tests/Unit/Attribute/EntityMetadataReaderTest.php` lines
  59, 65, 97, 107, 111 — unit tests calling with only `$class`.

Action: make `$entityTypeId` an optional 2nd parameter (nullable, default
`null`). Existing test calls compile and behave identically (resulting fields
keep empty `targetEntityTypeId`, matching today). No behavior change for
non-`fromClass()` paths.

## Changes

### 1. `packages/entity/src/Attribute/EntityMetadataReader.php`

- Change signature: `public static function resolveFields(string $class, ?string $entityTypeId = null): array`.
- Pass `targetEntityTypeId: $entityTypeId ?? ''` into the `FieldDefinition`
  constructor at line 104. The `?? ''` preserves the current default for
  callers that don't supply a type id.
- In `forClass()`, change line 35 to `$fields = self::resolveFields($class, $typeId);`
  so the already-resolved id flows through.

### 2. New regression test: `packages/entity/tests/Integration/EntityTypeRegistrationTest.php`

- Build a small `ContentEntityBase` subclass fixture with `#[ContentEntityType]`
  + at least one `#[Field]` property (reuse one of the existing test
  fixtures under `tests/Unit/Attribute/Fixtures` if it has both).
- Construct a real `FieldDefinitionRegistry`.
- Construct `EntityTypeManager(dispatcher, storageFactory, registry)`.
- Call `$manager->registerEntityType(EntityType::fromClass(Fixture::class))`.
- Assert that the registry now reports a core field with the expected
  `targetEntityTypeId`. This is the direct end-to-end check.
- Add a second assertion that constructs the type, inspects
  `$type->getFieldDefinitions()`, and confirms each field's
  `getTargetEntityTypeId()` matches the type id — guards against
  `EntityType::getFieldDefinitions()` regressions on the object path.

This test fails on `main` today (registry throws `InvalidArgumentException`
inside `registerCoreFields`) and passes after change #1.

### 3. Genealogy test refactor

- `packages/genealogy/tests/Unit/GenealogyFamilyServiceTest.php` — pass
  `$registry` as the 3rd argument to `new EntityTypeManager(...)` (line 37).
- `packages/genealogy/tests/Unit/GenealogyPedigreeServiceTest.php` — same
  refactor.
- Re-run both files; they must pass without further change. If anything else
  surfaces (e.g., `TestEntityType::stub()` field arrays not carrying the
  correct id), patch that too — the array-meta path already sets
  `targetEntityTypeId: $this->id` in `EntityType::getFieldDefinitions()`, so
  it should be transparent.

### 4. Doc cleanup

- `docs/specs/entity-system.md` §"Known Transitional Gaps" item 4 — remove
  the entry, or rewrite it as a "Resolved" footnote pointing at this
  mission's commit. Confirm the surrounding numbering.

## Acceptance gates

- `vendor/bin/phpunit packages/entity packages/field packages/genealogy` green.
- New integration test exists and exercises the real registry end-to-end.
- Genealogy tests pass with the registry wired through.
- `docs/specs/entity-system.md` no longer lists the gap as open.
- No new public API on `FieldDefinition` or `EntityType` beyond the
  optional 2nd param on `EntityMetadataReader::resolveFields()`.

## Risk register

- **Risk**: Cached `EntityClassMetadata` from prior runs returning fields
  without the type id.
  **Mitigation**: Cache key is the class name and the field array is built
  on cache miss; the type id never changes per class. Tests that already
  call `EntityMetadataReader::clearCache()` continue to work.
- **Risk**: An unexamined caller of `resolveFields()` outside `src/` and
  `tests/`.
  **Mitigation**: Default value is `null` and constructor receives `''` —
  bit-for-bit identical to today's behavior.

## Rollout

- One PR, one feature branch (lane created by `spec-kitty implement`).
- No migrations, no env flags, no compatibility shims required.
- Squash-merge into `main`.

## Out-of-scope (deferred)

- Auditing whether `FieldDefinition::__construct` should require
  `targetEntityTypeId` instead of defaulting to `''`. Tracked separately
  if the audit surfaces other empty-id construction sites.
- Touching `EntityType::getFieldDefinitions()` array-meta path — already
  correct.
