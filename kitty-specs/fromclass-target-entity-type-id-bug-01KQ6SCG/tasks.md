# Tasks — fromClass field definitions missing targetEntityTypeId

**Mission**: `fromclass-target-entity-type-id-bug-01KQ6SCG`
**Mission ID**: `01KQ6SCG3RCT25Q7WCEAKBFSTQ`
**Branch contract**: `main` → `main` (matches target).
**Work package count**: 2.

WPs split on disjoint file ownership: WP01 owns the `entity` package (fix +
new integration test); WP02 owns the genealogy tests and the framework
spec doc. WP02 depends on WP01 because its assertion (genealogy tests pass
with the registry wired through) requires the fix to be in place.

---

## Subtask Index

| ID | Description | WP | Parallel |
|----|-------------|----|----------|
| T001 | Add `?string $entityTypeId = null` parameter to `EntityMetadataReader::resolveFields()` and pass it as `targetEntityTypeId: $entityTypeId ?? ''` into the `FieldDefinition` constructor | WP01 | [D] |
| T002 | Update `EntityMetadataReader::forClass()` to call `self::resolveFields($class, $typeId)` so the already-resolved id flows through | WP01 | [D] |
| T003 | Create `packages/entity/tests/Integration/EntityTypeRegistrationTest.php` exercising `EntityType::fromClass()` + real `FieldDefinitionRegistry` + `EntityTypeManager::registerEntityType()` end-to-end; assert each registered field's `getTargetEntityTypeId()` matches the entity type id | WP01 | [D] |
| T004 | Verify existing `EntityMetadataReaderTest` cases still pass with the new optional parameter (no source change expected) | WP01 | [D] |
| T005 | Pass `$registry` as the 3rd argument to `new EntityTypeManager(...)` in `packages/genealogy/tests/Unit/GenealogyFamilyServiceTest.php::makeManager()`; remove any workaround comment | WP02 | [D] |
| T006 | Same registry wiring in `packages/genealogy/tests/Unit/GenealogyPedigreeServiceTest.php` | WP02 | [D] |
| T007 | Run `vendor/bin/phpunit packages/genealogy` and confirm green; investigate if any sibling test surfaces the same issue | WP02 | [D] |
| T008 | Remove (or rewrite as "Resolved") item 4 of `docs/specs/entity-system.md` §"Known Transitional Gaps"; verify section numbering remains consistent | WP02 | [D] |

---

## Work Package Map

| WP | Title | Dependencies | Owned files |
|----|-------|--------------|-------------|
| WP01 | EntityMetadataReader threads targetEntityTypeId; integration test | — | `packages/entity/src/Attribute/EntityMetadataReader.php`, `packages/entity/tests/Integration/EntityTypeRegistrationTest.php` (new) |
| WP02 | Genealogy tests wire registry; doc cleanup | WP01 | `packages/genealogy/tests/Unit/GenealogyFamilyServiceTest.php`, `packages/genealogy/tests/Unit/GenealogyPedigreeServiceTest.php`, `docs/specs/entity-system.md` |

---

## Requirement Mapping

| Requirement | WP(s) |
|-------------|-------|
| FR-1 (resolveFields threads id) | WP01 (T001) |
| FR-2 (forClass passes id) | WP01 (T002) |
| FR-3 (regression test) | WP01 (T003) |
| FR-4 (genealogy refactor) | WP02 (T005, T006, T007) |
| FR-5 (entity-system.md cleanup) | WP02 (T008) |

---

## Work Package Details

### WP01 — EntityMetadataReader threads targetEntityTypeId

**Goal**: Make `EntityMetadataReader::resolveFields()` accept the resolved
entity type id and apply it to every `FieldDefinition` it constructs. Add
the missing integration coverage that exercises `fromClass()` + a real
`FieldDefinitionRegistry` end-to-end.

**Priority**: P0 (mission core).

**Independent test**: New `packages/entity/tests/Integration/EntityTypeRegistrationTest.php`
passes; no failure inside `FieldDefinitionRegistry::registerCoreFields`.
Existing `packages/entity/tests/Unit/Attribute/EntityMetadataReaderTest.php`
still green (no source change required — the new parameter is optional).

**Approach**:
1. Write the integration test FIRST so it fails on `main` for the documented
   reason (`InvalidArgumentException` from the registry, message starting
   "Core field ... declares targetEntityTypeId ''").
2. Apply the two-line fix in `EntityMetadataReader` (T001 + T002).
3. Re-run; assert pass.

**Included subtasks**: T001, T002, T003, T004.

**Dependencies**: None.

**Owned files** (disjoint from WP02):
- `packages/entity/src/Attribute/EntityMetadataReader.php`
- `packages/entity/tests/Integration/EntityTypeRegistrationTest.php` (new)

**Prompt**: [`tasks/WP01-entitymetadatareader-fix.md`](./tasks/WP01-entitymetadatareader-fix.md)

---

### WP02 — Genealogy tests wire registry; doc cleanup

**Goal**: Restore the registry → `EntityTypeManager` link in the genealogy
test suite (now that the fix from WP01 makes it safe), and close out item
4 in `docs/specs/entity-system.md` §"Known Transitional Gaps".

**Priority**: P1 (closes the documented gap; required for FR-4 / FR-5).

**Independent test**: `vendor/bin/phpunit packages/genealogy` green with the
registry passed as the 3rd `EntityTypeManager` constructor argument in both
`GenealogyFamilyServiceTest` and `GenealogyPedigreeServiceTest`. The
"Known Transitional Gaps" section in `docs/specs/entity-system.md` no
longer lists item 4 as open.

**Included subtasks**: T005, T006, T007, T008.

**Dependencies**: WP01 (the fix must land before the registry can be wired
through without throwing).

**Owned files** (disjoint from WP01):
- `packages/genealogy/tests/Unit/GenealogyFamilyServiceTest.php`
- `packages/genealogy/tests/Unit/GenealogyPedigreeServiceTest.php`
- `docs/specs/entity-system.md`

**Prompt**: [`tasks/WP02-genealogy-and-docs.md`](./tasks/WP02-genealogy-and-docs.md)
