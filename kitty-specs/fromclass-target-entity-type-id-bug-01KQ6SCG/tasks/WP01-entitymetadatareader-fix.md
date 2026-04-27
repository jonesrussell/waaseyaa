---
work_package_id: WP01
title: EntityMetadataReader threads targetEntityTypeId
dependencies: []
requirement_refs:
- FR-1
- FR-2
- FR-3
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-fromclass-target-entity-type-id-bug-01KQ6SCG
base_commit: 5bf074515b7b6f468d802bb42172027e6398e48c
created_at: '2026-04-27T08:32:47.835850+00:00'
subtasks:
- T001
- T002
- T003
- T004
phase: Phase 1 - Core fix
assignee: ''
agent: "claude"
shell_pid: "15544"
authoritative_surface: packages/entity/src/Attribute/EntityMetadataReader
execution_mode: code_change
mission_id: 01KQ6SCG3RCT25Q7WCEAKBFSTQ
mission_slug: fromclass-target-entity-type-id-bug-01KQ6SCG
owned_files:
- packages/entity/src/Attribute/EntityMetadataReader.php
- packages/entity/tests/Integration/EntityTypeRegistrationTest.php
tags: []
---

# Work Package Prompt: WP01 — EntityMetadataReader threads targetEntityTypeId

## Objective

Make `EntityMetadataReader::resolveFields()` accept the resolved entity type
id and apply it to every `FieldDefinition` it constructs. This closes the
gap that causes `EntityType::fromClass(X)` →
`EntityTypeManager::registerEntityType($type)` to throw inside
`FieldDefinitionRegistry::registerCoreFields()` because the registered fields
default to `targetEntityTypeId = ''`.

## Context

Bug walk-through (read [spec.md](../spec.md) and [plan.md](../plan.md) for
the full picture):

1. `EntityMetadataReader::resolveFields()` (today) constructs `FieldDefinition`
   without `targetEntityTypeId` → defaults to `''`.
2. `EntityType::fromClass()` stuffs that map into `_fieldDefinitions`.
3. `EntityType::getFieldDefinitions()` passes `FieldDefinitionInterface`
   instances through unchanged via `instanceof` short-circuit.
4. `EntityTypeManager::persistDefinition()` calls
   `$this->fieldRegistry?->registerCoreFields($type->id(), $type->getFieldDefinitions())`.
5. `FieldDefinitionRegistry::registerCoreFields()` throws
   `InvalidArgumentException` because `'' !== $entityTypeId`.

The id IS already resolved at the top of `EntityMetadataReader::forClass()`
before `resolveFields()` is called — this is just signature plumbing.

## Subtasks

### T001 — Extend `resolveFields()` signature

In `packages/entity/src/Attribute/EntityMetadataReader.php`:

- Change signature to:
  `public static function resolveFields(string $class, ?string $entityTypeId = null): array`
- In the `FieldDefinition` constructor call (currently lines 104-116) add:
  `targetEntityTypeId: $entityTypeId ?? '',`

The `?? ''` preserves bit-for-bit current behavior for callers that don't
pass the id (the existing `EntityMetadataReaderTest` cases).

### T002 — Pass typeId through `forClass()`

In the same file, change the `forClass()` body where it calls
`resolveFields($class)` to pass `$typeId` as the second argument. The id is
resolved earlier in the same method so it's already in scope.

### T003 — New regression test

Create `packages/entity/tests/Integration/EntityTypeRegistrationTest.php`:

- Use a small `ContentEntityBase` fixture with `#[ContentEntityType]` and at
  least one `#[Field]` typed property. Reuse a fixture from
  `tests/Unit/Attribute/Fixtures/` if one fits; otherwise add a tiny one
  alongside the test (under integration test fixtures, not the unit
  fixtures).
- Build a real `FieldDefinitionRegistry`.
- Build `new EntityTypeManager($dispatcher, $storageFactoryOrNoop, $registry)`.
  If a no-op storage factory is needed, mirror the pattern in
  `packages/genealogy/tests/Unit/GenealogyFamilyServiceTest::makeManager()`.
- Call `$manager->registerEntityType(EntityType::fromClass(Fixture::class))`.
- Assert: `$registry->getCoreFields($entityTypeId)` returns the expected
  fields and each one's `getTargetEntityTypeId()` equals the entity type id.
- Add a parallel direct assertion: build the type with `fromClass()`, call
  `$type->getFieldDefinitions()`, iterate and assert
  `$field->getTargetEntityTypeId() === $type->id()` for each.

The first assertion is the regression guard for the actual bug; the second
guards `EntityType::getFieldDefinitions()` against future regressions on the
object path.

Verify the test fails on `main` BEFORE applying T001/T002 (red), then passes
after (green). Capture the failing message string in the WP history if it
helps the reviewer cross-check.

### T004 — Verify existing reader tests

Run `vendor/bin/phpunit packages/entity/tests/Unit/Attribute/EntityMetadataReaderTest.php`.
Expected: green with no source change. The new parameter has a default, and
those tests don't pass an id — they continue to receive fields with empty
`targetEntityTypeId`, which is the same behavior as today.

## Acceptance

- [ ] `vendor/bin/phpunit packages/entity` green.
- [ ] New integration test exists, exercises the real registry, fails on
      `main` pre-fix and passes post-fix.
- [ ] No new public API on `FieldDefinition` or `EntityType`.
- [ ] No change to the `instanceof FieldDefinitionInterface` branch in
      `EntityType::getFieldDefinitions()` (it stays correct because the
      objects flowing in now carry the right id).

## Out of scope (explicit)

- Genealogy test refactor — owned by WP02.
- Doc cleanup of `entity-system.md` — owned by WP02.
- Auditing other `FieldDefinition` construction sites for missing target id.

## Activity Log

- 2026-04-27T08:37:08Z – claude – shell_pid=24164 – Red→green TDD verified. Reproduced bug message exactly, applied two-line fix, re-ran: entity package 360/360.
- 2026-04-27T08:39:57Z – claude – shell_pid=15544 – Started review via action command
- 2026-04-27T08:42:24Z – claude – shell_pid=15544 – Independent code review (APPROVE-WITH-NITS): fix is mechanically sound, integration test exercises both object-path and manager-path with real registry, no public API changes beyond optional reader param.
