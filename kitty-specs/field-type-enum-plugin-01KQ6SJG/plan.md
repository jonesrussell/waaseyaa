# Implementation Plan: Enum Field-Type Plugin

**Mission**: `field-type-enum-plugin-01KQ6SJG`
**Branch**: planning + merge target = `main`
**Date**: 2026-04-27
**Spec**: [spec.md](./spec.md)
**Research**: [research.md](./research.md)

---

## Summary

Introduce an `enum` field-type plugin (`EnumItem`) that owns the contract for backed-enum-typed fields end to end: storage column shape, validation, JSON Schema emission for `waaseyaa/ai-schema`, and admin-widget option labels. Refactor `FieldTypeInferrer` to emit `'enum'` and remove the transitional `'string' + settings.enum_class` bridge documented in `docs/specs/entity-system.md` §"Known Transitional Gaps". Per discovery Q1=A and spec C-004, this is a hard cutover — no fallback path remains.

The migration surface is narrower than the spec assumed: research (R3) confirmed no production entity classes currently declare backed-enum fields via the bridge; only the inferrer, the constraint builder, and their tests need updating.

## Technical Context

**Language/Version**: PHP 8.4+
**Primary Dependencies**: Symfony 7 DI; Doctrine DBAL; existing `packages/field` and `packages/entity` framework packages
**Storage**: SQL via `DatabaseInterface` / `SqlStorageDriver` (no schema-format change in this mission; only column-type selection per backed-enum)
**Testing**: PHPUnit (existing convention under `packages/*/tests/`)
**Target Platform**: Server-side PHP runtime
**Project Type**: PHP framework monorepo (single project, multiple `packages/*`)
**Performance Goals**: Hydration of an enum-typed field is O(1) per value (single `from()` cast); no per-row reflection (NFR-001)
**Constraints**: Hard cutover — no fallback path may remain after merge (C-004); plugin file path, id, label, and settings shape are fixed (C-001..C-003); unit (non-backed) enums remain unsupported (C-006)
**Scale/Scope**: One new plugin class, one optional interface, two interface-method additions on `FieldTypeInterface`, one inferrer refactor, one constraint-builder refactor, one doc update; ~6 source files plus tests

## Decisions Recorded (from research)

| Topic | Decision | Source |
|-------|----------|--------|
| Plugin location | `packages/field/src/Item/EnumItem.php` | spec C-001 |
| Plugin attribute | `#[FieldType(id: 'enum', label: 'Enum')]` | spec C-002 |
| Settings shape | `settings: ['enum_class' => MyEnum::class]` (single key, no aliases) | spec C-003 |
| Storage column | string column for string-backed, integer column for int-backed | spec FR-003 |
| Discovery | Auto via `AttributeDiscovery` scanning `packages/field/src/Item/` | research R1 |
| Plugin base | `FieldItemBase` (existing) | research R1 |
| Widget label seam | Static helper `EnumItem::casesForEnumClass(string)` + optional `LabeledCase` interface | research R2 |
| JSON Schema seam | Add `FieldTypeInterface::jsonSchemaFor(FieldDefinitionInterface): array`; delegate from `FieldDefinition::toJsonSchema()` via `FieldTypeManager` | research R5 |
| Storage shape seam | Add `FieldTypeInterface::schemaFor(FieldDefinitionInterface): array` parallel to `jsonSchemaFor` (static `schema()` cannot read settings) | research R6 |
| Inferrer change | `packages/entity/src/Attribute/FieldTypeInferrer.php:144-148` returns `'enum'`; add `'enum'` to `VALID_TYPE_IDS` | research R4 |
| Constraint builder | Refactor `packages/entity/src/Validation/FieldDefinitionConstraintBuilder.php:67-78` to use the plugin path for `'enum'` fields and remove the `'string' + enum_class` legacy branch | research R3 |
| Doc update | Mark bridge closed in `docs/specs/entity-system.md` §"Known Transitional Gaps", reference this mission | spec FR-011 |

## Charter Check

Charter context for action `plan` was not loaded as a separate gate during this draft. **Action item**: planner runs `spec-kitty charter context --action plan --json` from `C:\Users\jones\Projects\Rainbow\waaseyaa-framework` before `/spec-kitty.tasks` and reconciles any gates surfaced. If the charter is absent, this gate is skipped per the missing-charter rule. No expected conflicts: the mission is internal framework refactoring with no public-API surface changes beyond a new plugin and additive interface methods (defaults preserve existing behavior).

## Project Structure

### Documentation (this feature)

```
kitty-specs/field-type-enum-plugin-01KQ6SJG/
├── spec.md                # Mission spec (already authored)
├── plan.md                # This file
├── research.md            # Phase 0 output
├── data-model.md          # Phase 1 output
├── quickstart.md          # Phase 1 output
├── contracts/             # Phase 1 output (PHP method contracts)
├── checklists/
│   └── requirements.md    # Spec-quality checklist (already authored)
└── tasks/                 # Created by /spec-kitty.tasks
```

### Source Code (repository root, waaseyaa-framework)

Files to be added:

```
packages/field/src/Item/
├── EnumItem.php           # NEW — the plugin
└── LabeledCase.php        # NEW — optional one-method interface for enums opting into custom labels (final location TBD; could live elsewhere under packages/field/src/)

packages/field/tests/Unit/Item/
└── EnumItemTest.php       # NEW — unit coverage per NFR-003
```

Files to be modified:

```
packages/field/src/FieldTypeInterface.php             # add jsonSchemaFor + schemaFor (with default impls)
packages/field/src/FieldItemBase.php                  # default impls of jsonSchemaFor + schemaFor
packages/field/src/FieldDefinition.php                # toJsonSchema() delegates via FieldTypeManager
packages/field/src/FieldTypeManager.php               # add jsonSchemaFor() / schemaFor() helpers
packages/entity/src/Attribute/FieldTypeInferrer.php   # emit 'enum'; add to VALID_TYPE_IDS
packages/entity/src/Attribute/Field.php               # docstring example updated
packages/entity/src/Validation/FieldDefinitionConstraintBuilder.php  # plugin-delegated enum path; remove legacy branch
docs/specs/entity-system.md                           # §"Known Transitional Gaps" — close bridge entry
packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php       # assert 'enum' shape
```

**Structure Decision**: PHP framework monorepo with package-per-concern under `packages/`. Plugin lives in `packages/field/`; the inferrer and constraint builder live in `packages/entity/`. Tests live alongside their packages.

## Architecture Sketch

```
                                      attribute-first entity
                                              |
                                              v
                       +-------------------------+
                       | FieldTypeInferrer       |  (refactored: returns 'enum')
                       +-------------------------+
                                              |
                                              v
                                  FieldDefinition (type='enum',
                                                   settings={enum_class: FQCN})
                                              |
                +-----------------+-----------+----------+----------------+
                v                 v                      v                v
        +---------------+  +-------------+        +--------------+  +-------------+
        | EnumItem      |  | EnumItem    |        | EnumItem     |  | EnumItem    |
        | ::schemaFor() |  | ::jsonSchema|        | ::cases-     |  | (validation |
        | (storage      |  | For()       |        | ForEnumClass |  | hooks)      |
        | column)       |  | (ai-schema) |        | (admin       |  +-------------+
        +---------------+  +-------------+        | widgets &    |
                                                  | constraint   |
                                                  | builder)     |
                                                  +--------------+
```

All four downstream consumers ask the plugin; the plugin asks the enum class. Single source of truth.

## Implementation Sequencing (preview, not WP-final)

Final WP breakdown happens during `/spec-kitty.tasks`. Anticipated grouping:

1. **Foundation interface seams** — extend `FieldTypeInterface` with `jsonSchemaFor()` and `schemaFor()` defaults; refactor `FieldDefinition::toJsonSchema()` and the storage-schema caller to delegate via `FieldTypeManager`. Existing field types unaffected (defaults preserve behavior).
2. **EnumItem plugin** — create the plugin class; implement `schemaFor`/`jsonSchemaFor`/`defaultSettings`/validation/coercion/case-helpers; add the optional `LabeledCase` interface.
3. **Inferrer refactor** — flip `FieldTypeInferrer.php:144-148` to return `'enum'`; add `'enum'` to `VALID_TYPE_IDS`; update inferrer tests.
4. **Constraint builder migration** — refactor `FieldDefinitionConstraintBuilder.php:67-78` to delegate enum validation to the plugin path; remove the legacy `'string' + enum_class` branch.
5. **Documentation** — close the entity-system.md transitional-gap entry; update the `Field` attribute docstring; CHANGELOG entry if the repo maintains one.
6. **Verification** — grep sweep across the monorepo asserting no `enum_class` references remain outside the plugin, inferrer (test fixtures), constraint builder, and tests; run full test suite.

## Risks

| Risk | Mitigation |
|------|------------|
| Adding `schemaFor()`/`jsonSchemaFor()` to `FieldTypeInterface` is a breaking change for downstream framework consumers implementing the interface directly. | Default base implementations on `FieldItemBase` preserve current behavior (delegate to existing static `schema()`/`jsonSchema()`). Only `EnumItem` overrides them. |
| Storage layer caller of `schema()` may not pass a `FieldDefinition`. | Trace caller path during WP1; if no per-definition entry exists, the WP scope expands to thread `FieldDefinitionInterface` through that call site. |
| `FieldDefinition::toJsonSchema()` is exercised by tests; behavior must remain bit-identical for non-enum types. | Existing types' `jsonSchemaFor()` defaults must produce the same output as the current hardcoded `match`. WP1 includes a regression assertion suite. |
| A future external consumer reaches into raw `enum_class` settings directly. | Out of scope per C-005 (no consumer-app changes); flagged in CHANGELOG as a breaking change if/when one exists. |
| Static `defaultSettings(): array` cannot enforce that `enum_class` is set. | Validate at first plugin instantiation against the field definition (FR-008) — error if `null` or unset. |

## Out of Scope (re-affirming spec)

- Unit (non-backed) enum support (C-006).
- Refactoring other field types (`string`, `integer`, etc.) to use `jsonSchemaFor()`/`schemaFor()` overrides — only `enum` needs them now; defaults preserve behavior.
- Sibling-repo consumer migration (C-005).

## Complexity Tracking

No charter violations identified. Complexity additions (new interface methods, new delegation seam in `FieldDefinition::toJsonSchema`) are justified by FR-006 — the plugin must own the JSON-Schema fragment for enums; there is no simpler way to get `enum: [...]` into the emitted schema without re-introducing a bridge-shaped pattern.

## Branch Contract (re-stated)

- Current branch at plan start: **main**
- Planning/base branch: **main**
- Final merge target: **main**
- `branch_matches_target`: **true**
