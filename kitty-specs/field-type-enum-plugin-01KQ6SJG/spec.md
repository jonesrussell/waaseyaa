# Mission Specification: Enum Field-Type Plugin

**Mission ID**: `01KQ6SJGTFFN73ZS746T06NMFV`
**Mission Slug**: `field-type-enum-plugin-01KQ6SJG`
**Created**: 2026-04-27
**Mission Type**: software-dev
**Target Branch**: `main`

---

## Overview

The framework's field-type plugin registry currently has no first-class representation for PHP backed enums. Authors of entity classes that want to constrain a string or integer field to a fixed set of values declare the field as `'string'` (or `'integer'`) and attach a `settings.enum_class => MyEnum::class` hint. `FieldTypeInferrer` recognises backed enum return types in attribute-first entity definitions and emits exactly that pair as a transitional bridge.

That bridge — documented in `docs/specs/entity-system.md` §"Known Transitional Gaps" and traced in `kitty-specs/attribute-first-entity-definition-01KQ6DXE/research.md` R3 — leaves three gaps:

1. **Validation is not enforced at the plugin layer.** Invalid scalars can reach storage; conversion back to enum cases is left to consumers.
2. **JSON Schema emission for `waaseyaa/ai-schema` cannot constrain values to the case set**, so AI-generated payloads can produce illegal values that only fail downstream.
3. **Admin widgets have no canonical place to surface case labels**, so each form integration improvises label resolution from the enum class.

This mission introduces an `enum` field-type plugin (`EnumItem`) that closes those gaps and removes the transitional bridge.

## Motivation

- M1 (`attribute-first-entity-definition-01KQ6DXE`, merged at `ce123bfe`) made backed-enum field declarations idiomatic in entity classes via the `#[Field]` attribute path, which raised the rate at which the bridge is exercised.
- The bridge encodes enum semantics in two places (`'string'` type + `enum_class` setting) instead of one, so consumers (validators, schema emitter, admin widgets) each re-derive the same logic — a known violation of the framework's "one canonical representation" stance for field types.
- `waaseyaa/ai-schema` consumers want JSON Schemas with explicit `enum: [...]` constraints; today they have to special-case the `enum_class` setting at the schema layer instead of the field-type plugin owning that contract.

## User Scenarios & Testing

### Primary actors

- **Entity authors** declaring entity fields whose type is a PHP backed enum.
- **Field-type plugin consumers**: storage layer, validation layer, `waaseyaa/ai-schema` JSON Schema emitter, admin form widgets.
- **Framework maintainers** removing the transitional bridge documented in `entity-system.md`.

### Acceptance scenarios

1. **AS-1 — Authoring with a string-backed enum.** An entity author declares a field whose type is a string-backed enum. After this mission, the inferred field type is `enum` with `settings.enum_class` set; storage column is `string`; reads hydrate to enum cases; writes accept either an enum case or its backing scalar; invalid scalars are rejected before persistence.
2. **AS-2 — Authoring with an int-backed enum.** Same as AS-1 but the storage column is `integer`.
3. **AS-3 — JSON Schema emission.** When `waaseyaa/ai-schema` derives a JSON Schema for an entity with an enum field, the generated schema for that field includes `enum: [<case_value_1>, <case_value_2>, ...]` matching the backing scalars of every case in the declared enum.
4. **AS-4 — Admin widget labels.** When an admin form widget renders an enum field, the option list shows each case's human label, where the label resolves through the enum class's label provider if one exists, otherwise falls back to the case name.
5. **AS-5 — Storage-time rejection.** Persisting an entity whose enum field carries a value not present in the declared enum's case list fails validation with a clear plugin-attributed error before any database write is attempted.
6. **AS-6 — Inferrer migration.** `FieldTypeInferrer`, when given an attribute-first entity field whose type is a backed enum, returns field type `'enum'` with `settings.enum_class => <FQCN>` — never the legacy `'string' + settings.enum_class` pair.
7. **AS-7 — Consumer migration.** Every entity class in the monorepo that previously declared a backed-enum field via `'string' + settings.enum_class` (or the integer variant) now declares it via the `enum` field type. Grep for `enum_class` settings outside the new plugin and inferrer returns no remaining call sites.
8. **AS-8 — Hard cutover.** The transitional bridge — the code path in `FieldTypeInferrer` and any storage/validation code that special-cased `'string' + enum_class` — is removed. A field declared as `'string'` with `settings.enum_class` after this mission MUST NOT be silently re-interpreted as enum; if such a configuration exists it surfaces as a clear error or is rewritten to use `enum`.

### Edge cases

- **EC-1** Enum case backing value collides with another field-type's coercion rules (e.g. an int-backed enum case `0` vs. boolean-like coercion). Plugin must coerce against the declared enum, not generic scalar coercion.
- **EC-2** Hydrating a stored value whose backing scalar is no longer a valid case (enum was edited after data was written). Plugin must surface a deterministic error rather than silently returning `null`.
- **EC-3** `enum_class` setting points to a non-existent class, a non-enum class, or a unit (non-backed) enum. Plugin must reject at registration / first-use with a clear diagnostic.
- **EC-4** Mixed enum class shapes in the same field across migrations. Out of scope: a field's `enum_class` is fixed for its lifetime; changing it is a schema migration, not runtime polymorphism.
- **EC-5** JSON Schema emission when the enum has no cases (empty enum). Schema MUST reflect an empty `enum: []` and validation MUST treat any value as invalid.

## Requirements

### Functional Requirements

| ID | Requirement | Status |
|----|-------------|--------|
| FR-001 | Provide an `EnumItem` field-type plugin at `packages/field/src/Item/EnumItem.php` decorated with `#[FieldType(id: 'enum', label: 'Enum')]`. | Approved |
| FR-002 | The plugin MUST accept the enum class via the field's `settings: ['enum_class' => MyEnum::class]` configuration and treat that class as authoritative for case enumeration. | Approved |
| FR-003 | Storage representation MUST be the backing scalar of the enum: `string` column for string-backed enums, `integer` column for int-backed enums. The plugin MUST select this based on introspection of `enum_class`. | Approved |
| FR-004 | Hydration (database → entity) MUST cast the stored scalar to the corresponding enum case. A scalar that does not match any case MUST raise a deterministic error attributable to the plugin. | Approved |
| FR-005 | Mutation (entity → storage) MUST accept either an enum case instance or its backing scalar, and MUST reject any other value before storage with a clear validation error. | Approved |
| FR-006 | The plugin MUST emit a JSON Schema fragment for `waaseyaa/ai-schema` consumers that includes an `enum: [...]` array listing the backing scalar of every case in declared order, plus the appropriate `type` (`"string"` or `"integer"`). | Approved |
| FR-007 | The plugin MUST surface case labels for admin form widgets through the framework's existing widget label-provider seam, resolving labels from the enum class's label provider when present and falling back to case name otherwise. | Approved |
| FR-008 | The plugin MUST validate `enum_class` at the earliest viable point: it must exist, be a backed enum (`BackedEnum`), and have a backing type of `string` or `int`. Invalid configuration MUST raise a clear diagnostic. | Approved |
| FR-009 | `FieldTypeInferrer` MUST map any backed-enum-typed entity field to field type `'enum'` with `settings.enum_class => <FQCN>`. The previous `'string' + settings.enum_class` (and integer variant) emission path MUST be removed. | Approved |
| FR-010 | Every existing entity class in the monorepo that declared a backed-enum field via the transitional `'string' + settings.enum_class` shape MUST be migrated to the `enum` field type. | Approved |
| FR-011 | The transitional bridge entry in `docs/specs/entity-system.md` §"Known Transitional Gaps" MUST be updated to reflect the bridge's removal (i.e. closed, with reference to this mission). | Approved |
| FR-012 | After migration, a grep across the monorepo for `enum_class` MUST yield matches only inside the new plugin, the inferrer, and tests/fixtures — no entity class should still be declaring `'string' + settings.enum_class`. | Approved |

### Non-Functional Requirements

| ID | Requirement | Status |
|----|-------------|--------|
| NFR-001 | Hydration of an enum-typed field MUST not add measurable overhead beyond a single `from()` cast per value (i.e. O(1), no per-row reflection). | Approved |
| NFR-002 | Plugin diagnostics (invalid `enum_class`, invalid stored scalar, invalid input value) MUST identify the offending field name and enum class in the error message to keep debugging cost low. | Approved |
| NFR-003 | The plugin MUST be unit-test covered for: string-backed happy path, int-backed happy path, invalid stored scalar, invalid input value, invalid `enum_class` configuration, JSON Schema shape, and widget label resolution. | Approved |
| NFR-004 | The inferrer refactor MUST be covered by tests that previously exercised the `'string' + enum_class` bridge, updated to assert the new `'enum'` shape. | Approved |

### Constraints

| ID | Constraint | Status |
|----|------------|--------|
| C-001 | Plugin file location is fixed: `packages/field/src/Item/EnumItem.php`. | Approved |
| C-002 | Plugin id and label are fixed: `#[FieldType(id: 'enum', label: 'Enum')]`. | Approved |
| C-003 | Settings shape is fixed: `settings: ['enum_class' => MyEnum::class]`. The mission MUST NOT introduce parallel settings keys (e.g. `enum`, `class`, `cases`). | Approved |
| C-004 | Hard cutover: the `'string' + settings.enum_class` (and integer variant) bridge MUST be removed in this mission, not deprecated. No fallback path may remain after merge. | Approved |
| C-005 | Mission lives in `waaseyaa-framework` repo; no changes to consuming applications (e.g. `course-journey`) are in scope here. | Approved |
| C-006 | The mission MUST NOT introduce unit (non-backed) enum support. Unit enums remain unsupported as field types. | Approved |

## Success Criteria

| ID | Outcome | Measure |
|----|---------|---------|
| SC-001 | Backed-enum fields are declared once, in one canonical shape (`enum` field type + `enum_class` setting). | Grep across the monorepo finds zero `enum_class` settings paired with `'string'` or `'integer'` field types outside test fixtures explicitly exercising error paths. |
| SC-002 | AI-generated payloads against entities with enum fields can be statically constrained to legal cases. | The JSON Schema produced by `waaseyaa/ai-schema` for any enum-typed field contains an `enum: [...]` array equal to the case backing values, verified by a unit test per AS-3. |
| SC-003 | Invalid enum values fail closed at the plugin boundary. | Persisting a non-case scalar through the entity → storage path raises a plugin-attributed validation error with the field name and enum class included (per FR-005, NFR-002), verified by a unit test. |
| SC-004 | The transitional bridge is closed. | `docs/specs/entity-system.md` §"Known Transitional Gaps" no longer lists the enum bridge as open; the inferrer code path that emitted `'string' + enum_class` is deleted; tests asserting the new `'enum'` emission shape pass. |
| SC-005 | Admin widgets show human-meaningful case labels without per-call-site label-resolution code. | A unit/integration test renders an admin widget for an enum field whose enum class declares custom labels and asserts those labels appear in the option list. |

## Key Entities

- **EnumItem** (new) — field-type plugin class at `packages/field/src/Item/EnumItem.php`. Holds enum-class introspection, scalar↔case coercion, JSON Schema emission, and widget label surfacing.
- **`enum_class` setting** — single source of truth for which enum a field constrains to; FQCN of a `BackedEnum` subtype.
- **FieldTypeInferrer** — refactored consumer; maps backed-enum-typed fields to `'enum'`.
- **Entity classes carrying `enum_class` settings** (to be discovered via grep during plan/research) — migrated to declare the `enum` field type.
- **`waaseyaa/ai-schema` JSON Schema emitter** — downstream consumer of the new plugin's schema fragment; not modified directly except where it currently special-cases `enum_class` on `'string'` fields.

## Assumptions

- The framework's existing `#[FieldType]` attribute and plugin-discovery mechanism is sufficient to register `EnumItem` without bespoke wiring; if not, plan phase identifies the smallest registration change needed.
- The framework already has a widget label-provider seam (or comparable extension point) on existing field-type plugins; if not, plan phase decides whether to add one or surface labels via a plugin-method convention.
- No existing data uses the integer-backed shape today (it has only string-backed precedent in real entities). The plan phase will confirm via grep; the spec covers both shapes regardless.
- "Hard cutover" (per Q1=A) means in-tree consumers only — i.e. everything reachable from this monorepo. External downstream consumers of the framework, if any, will see this as a breaking change documented in CHANGELOG/entity-system.md.

## Dependencies

- M1 milestone `attribute-first-entity-definition-01KQ6DXE` (merged at `ce123bfe`) — provides the attribute-first definition path that the inferrer operates over.
- Existing field-type plugin registry, storage drivers, and `waaseyaa/ai-schema` JSON Schema emitter — all touched as consumers but their architectures remain intact.

## Out of Scope

- Unit (non-backed) enum support.
- Enum-class evolution / migration tooling for renamed or removed cases beyond surfacing a deterministic hydration error (EC-2).
- Changes to consumer applications in sibling repos (e.g. `course-journey`); they will adopt naturally when they bump their framework dependency.
- New widget UI affordances beyond labelled option lists (e.g. searchable comboboxes, grouped options).

## References

- Transitional bridge: `docs/specs/entity-system.md` §"Known Transitional Gaps"
- Background research: `kitty-specs/attribute-first-entity-definition-01KQ6DXE/research.md` R3
- Predecessor mission: `attribute-first-entity-definition-01KQ6DXE`, merged at commit `ce123bfe`
