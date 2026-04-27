# Tasks: Enum Field-Type Plugin

**Mission**: `field-type-enum-plugin-01KQ6SJG`
**Branch**: planning + merge target = `main`
**Date**: 2026-04-27

This file decomposes [plan.md](./plan.md) into work packages. Each WP has its own prompt under `tasks/WP##-*.md`.

## Branch Strategy

- Current branch at start: `main`
- Planning/base branch: `main`
- Final merge target: `main`
- Execution worktrees are allocated per computed lane after `finalize-tasks`; consult `lanes.json` from the next-step loop.

## Subtask Index

| ID | Description | WP | Parallel |
|----|-------------|----|----------|
| T001 | Add `jsonSchemaFor(FieldDefinitionInterface): array` and `schemaFor(FieldDefinitionInterface): array` to `FieldTypeInterface` | WP01 |  |
| T002 | Add default implementations on `FieldItemBase` that delegate to existing static `jsonSchema()` / `schema()` | WP01 |  |
| T003 | Add `jsonSchemaFor()` and `schemaFor()` helpers on `FieldTypeManager` that resolve plugin and forward | WP01 |  |
| T004 | Refactor `FieldDefinition::toJsonSchema()` to delegate via `FieldTypeManager::jsonSchemaFor($this)`; thread `FieldTypeManager` through construction | WP01 |  |
| T005 | Add regression test asserting `FieldDefinition::toJsonSchema()` output is bit-identical for every existing field-type id | WP01 |  |
| T006 | Create `LabeledCase` interface at `packages/field/src/Item/LabeledCase.php` (single method `getLabel(): string`) | WP02 |  |
| T007 | Create `EnumItem` plugin at `packages/field/src/Item/EnumItem.php` with `#[FieldType(id: 'enum', label: 'Enum')]` extending `FieldItemBase` | WP02 |  |
| T008 | Implement `EnumItem::defaultSettings()` and runtime configuration validation (`MissingEnumClass`, `UnknownEnumClass`, `NotABackedEnum`, `UnsupportedBackingType`) | WP02 |  |
| T009 | Implement `EnumItem::schemaFor(FieldDefinitionInterface)` and `EnumItem::jsonSchemaFor(FieldDefinitionInterface)` per data-model.md §2 and §3 | WP02 |  |
| T010 | Implement `EnumItem::castToCase()`, `EnumItem::hydrate()`, and `EnumItem::casesForEnumClass()` per contracts/enum-item.md | WP02 |  |
| T011 | Write `EnumItemTest` covering NFR-003 surface (string/int happy paths, invalid stored, invalid input, invalid enum_class config, JSON Schema shape, label resolution, empty-enum EC-5) | WP02 |  |
| T012 | Add `'enum'` to `VALID_TYPE_IDS` in `FieldTypeInferrer.php` (lines 27–44) | WP03 | [P] |
| T013 | Flip `FieldTypeInferrer.php:144-148` emission from `'string'` to `'enum'` | WP03 | [P] |
| T014 | Update `Field` attribute docstring in `packages/entity/src/Attribute/Field.php` to show `enum` field-type example | WP03 | [P] |
| T015 | Update `FieldTypeInferrerTest.php` (lines 78–85, 139–148, 150) to assert new `'enum' + enum_class` shape; reconsider `explicit_string_type_on_backed_enum_keeps_inferred_enum_class` per AS-8 (now an error path) | WP03 | [P] |
| T016 | Refactor `FieldDefinitionConstraintBuilder.php:67-78` to scope enum logic to `$def->getType() === 'enum'` and delegate case-value lookup to `EnumItem::casesForEnumClass()` | WP04 | [P] |
| T017 | Remove the legacy `enumClass` alias read; remove the `'string' + enum_class` legacy branch entirely (C-004) | WP04 | [P] |
| T018 | Update or add tests under `packages/entity/tests/Unit/Validation/` to assert: `'enum'` field produces `Choice` constraint with case backing values; `'string' + enum_class` no longer adds a `Choice` constraint | WP04 | [P] |
| T019 | Close the transitional-gap entry for the enum bridge in `docs/specs/entity-system.md` §"Known Transitional Gaps", referencing this mission | WP05 |  |
| T020 | Add CHANGELOG entry (if `CHANGELOG.md` exists at repo root) describing the new `enum` field type and the breaking refactor of `FieldTypeInferrer`/`FieldDefinitionConstraintBuilder`; if no CHANGELOG, skip and document the skip in the WP | WP05 |  |
| T021 | Final grep sweep: assert no `enum_class` references remain outside `EnumItem.php`, `FieldTypeInferrer.php`, `FieldDefinitionConstraintBuilder.php`, and tests/fixtures (SC-001) | WP05 |  |

---

## WP01 — Foundation interface seams

**Goal**: Add the `jsonSchemaFor()` / `schemaFor()` per-definition seams to the field-type plugin contract and route `FieldDefinition::toJsonSchema()` through `FieldTypeManager`. Defaults preserve every existing field type's behavior.

**Priority**: P0 (foundation — every other WP depends on this)
**Independent test**: Existing test suite passes unchanged. New regression test confirms `FieldDefinition::toJsonSchema()` returns bit-identical output for `string`, `integer`, `boolean`, `float`, `text`, `entity_reference` field types.
**Dependencies**: none
**Estimated prompt size**: ~350 lines

**Subtasks**:

- [ ] T001 Add `jsonSchemaFor`/`schemaFor` to `FieldTypeInterface` (WP01)
- [ ] T002 Add default impls on `FieldItemBase` (WP01)
- [ ] T003 Add `FieldTypeManager` helpers (WP01)
- [ ] T004 Refactor `FieldDefinition::toJsonSchema()` to delegate (WP01)
- [ ] T005 Regression test for behavior preservation (WP01)

**Risks**:
- `FieldDefinition` may not currently hold a `FieldTypeManager` reference; threading one through construction might widen the diff.
- Implementations of `FieldTypeInterface` outside `FieldItemBase` (if any) become broken without the default. Search for direct implementers and address in this WP.

**Prompt**: [tasks/WP01-foundation-interface-seams.md](./tasks/WP01-foundation-interface-seams.md)

---

## WP02 — EnumItem plugin

**Goal**: Create the `EnumItem` plugin and the optional `LabeledCase` interface. The plugin owns storage column shape, JSON Schema fragment, validation, hydration, and case-label resolution for backed-enum fields.

**Priority**: P0 (the actual feature)
**Independent test**: `EnumItemTest` passes; the plugin is auto-discovered by `FieldTypeManager` and answers `id => 'enum'`.
**Dependencies**: WP01
**Estimated prompt size**: ~480 lines

**Subtasks**:

- [ ] T006 Create `LabeledCase` interface (WP02)
- [ ] T007 Create `EnumItem` plugin scaffold with `#[FieldType]` attribute (WP02)
- [ ] T008 Settings validation with full error taxonomy (WP02)
- [ ] T009 `schemaFor` + `jsonSchemaFor` overrides (WP02)
- [ ] T010 Coercion / hydration / cases helper (WP02)
- [ ] T011 Unit tests covering NFR-003 surface (WP02)

**Risks**:
- Storage layer may call static `schema()` rather than going through `schemaFor()`; trace `FieldTypeInterface::schema` callers and confirm WP01's delegation reaches the storage path. If not, scope expands to plumb through.
- `LabeledCase` interface placement may collide with namespace conventions; final location may need to be `packages/field/src/LabeledCase.php` rather than `Item/`. Confirm during implementation.

**Prompt**: [tasks/WP02-enum-item-plugin.md](./tasks/WP02-enum-item-plugin.md)

---

## WP03 — Inferrer refactor

**Goal**: Flip `FieldTypeInferrer` to emit `'enum'` for backed-enum-typed fields and remove the legacy `'string'` emission for that case.

**Priority**: P1 (gates entity-side adoption)
**Independent test**: `FieldTypeInferrerTest` passes with assertions on the new `'enum' + enum_class` shape; the legacy `explicit_string_type_on_backed_enum_keeps_inferred_enum_class` test is replaced with an assertion that explicit `type='string'` on a backed-enum property is now rejected (per AS-8).
**Dependencies**: WP02 (the plugin must exist before the inferrer emits its id)
**Parallel**: with WP04 (different files)
**Estimated prompt size**: ~280 lines

**Subtasks**:

- [ ] T012 [P] Add `'enum'` to `VALID_TYPE_IDS` (WP03)
- [ ] T013 [P] Flip emission line (WP03)
- [ ] T014 [P] Update `Field` attribute docstring (WP03)
- [ ] T015 [P] Update inferrer tests (WP03)

**Risks**:
- Other places in the entity package may also enumerate valid type ids; search for `VALID_TYPE_IDS` references.

**Prompt**: [tasks/WP03-inferrer-refactor.md](./tasks/WP03-inferrer-refactor.md)

---

## WP04 — Constraint builder migration

**Goal**: Refactor `FieldDefinitionConstraintBuilder` so enum validation only fires for `type='enum'` fields and delegates the case-value lookup to `EnumItem`. Remove the legacy `'string' + enum_class` branch and the `enumClass` alias.

**Priority**: P1 (enforces SC-003)
**Independent test**: validation-layer tests pass; a `Choice` constraint is added for `'enum'` fields, never for `'string' + enum_class` fields.
**Dependencies**: WP02
**Parallel**: with WP03
**Estimated prompt size**: ~250 lines

**Subtasks**:

- [ ] T016 [P] Scope enum logic to `type='enum'` and delegate to `EnumItem` (WP04)
- [ ] T017 [P] Remove legacy alias and bridge branch (WP04)
- [ ] T018 [P] Update validation tests (WP04)

**Risks**:
- `FieldDefinitionConstraintBuilder` may be called from multiple validators; make sure removing the `enumClass` alias doesn't silently strip behavior elsewhere. Search for `enumClass` (camelCase) usages.

**Prompt**: [tasks/WP04-constraint-builder-migration.md](./tasks/WP04-constraint-builder-migration.md)

---

## WP05 — Documentation and verification

**Goal**: Close the transitional-gap entry in `docs/specs/entity-system.md`, add a CHANGELOG entry (if applicable), and run the final grep sweep that proves SC-001 holds.

**Priority**: P2 (closes the mission's audit trail)
**Independent test**: grep across the monorepo finds no `enum_class` references outside the four allowed sites; `docs/specs/entity-system.md` no longer lists the bridge as open.
**Dependencies**: WP03, WP04
**Estimated prompt size**: ~220 lines

**Subtasks**:

- [ ] T019 Close transitional-gap entry (WP05)
- [ ] T020 CHANGELOG entry (or document skip) (WP05)
- [ ] T021 Final grep sweep (WP05)

**Risks**:
- A leftover `enum_class` hit may appear in docs or comments outside the four allowed sites; resolve by editing the doc rather than carving out an exception.

**Prompt**: [tasks/WP05-documentation-and-verification.md](./tasks/WP05-documentation-and-verification.md)

---

## MVP scope recommendation

WP01 + WP02 + WP03 + WP04 together deliver the full feature. WP05 is a closeout WP and should ship in the same merge to keep the audit trail clean (SC-001, SC-004). There is no smaller MVP — the spec mandates a hard cutover, so WP03 and WP04 cannot be deferred.
