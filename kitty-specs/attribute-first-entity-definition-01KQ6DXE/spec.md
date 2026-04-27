# Attribute-First Entity Definition — Specification

**Mission**: `attribute-first-entity-definition-01KQ6DXE`
**Mission ID**: `01KQ6DXEQ01S6PVPT6KF5946TA`
**Mission type**: `software-dev`
**Target branch**: `main`
**Created**: 2026-04-27
**Track position**: M1 of 2 (M2 is `attribute-entity-classmap-discovery`)

---

## 1. Problem & Motivation

Today, defining a content entity requires two declarations that must be kept in sync:

1. A class extending `ContentEntityBase` with a `#[ContentEntityType(id: '…')]` attribute and a constructor passing `$entityTypeId` / `$entityKeys` defaults.
2. A separate `EntityType(...)` registration in app config (e.g. `config/entity-types.php`) carrying `keys`, `fieldDefinitions`, etc.

`EntityTypeManager::assertClassMetadataMatchesEntityType()` exists solely to police that these two sources of truth haven't drifted. The validator's existence is the smell — the framework forces a duplication and then guards it. App authors discover the duplication only when registration throws. For a framework whose stated identity is "modern, entity-first, AI-native, no legacy debt," this is the highest-leverage debt to eliminate before more entity-defining apps ship.

**Greenfield context:** the framework is in alpha with only minimal R&D apps. There is no production code requiring backwards compatibility. The legacy `fieldDefinitions:` array path can be **removed**, not deprecated.

## 2. Users & Primary Scenarios

**Primary user**: a framework consumer (R&D app developer) defining entity types.

### Acceptance scenarios

**S1 — Define a content entity in one place**
1. Developer writes a single PHP class:
   ```php
   #[ContentEntityType(id: 'note', label: 'Note', description: 'Free-form authored content')]
   #[ContentEntityKeys(label: 'title', bundle: 'bundle', langcode: 'langcode')]
   final class Note extends ContentEntityBase {
       #[Field] public string $title;
       #[Field] public ?string $body;
       #[Field(default: 'draft')] public string $status;
   }
   ```
2. Developer registers via `EntityType::fromClass(Note::class)`.
3. The system produces a fully-formed `EntityType` with field definitions derived from the typed properties.
4. No duplicate field declarations exist anywhere in the codebase.

**S2 — Override defaults via attribute parameters**
1. A property has a non-trivial field shape (custom default, validator, JSON storage).
2. Developer uses `#[Field(default: ['draft'], type: 'json')]` to override inferred defaults.
3. The override applies; the property type still constrains PHP-level access.

**S3 — Inferred field type matches property type**
1. `public string $name` infers `type: 'string'`.
2. `public int $count` infers `type: 'integer'`.
3. `public bool $isActive` infers `type: 'boolean'`.
4. `public ?\DateTimeImmutable $publishedAt` infers `type: 'datetime'` and `required: false` (nullable).
5. `public CourseStatus $status` (where `CourseStatus` is a backed enum) infers `type: 'enum'` with the enum's value type.
6. `public array $tags` infers `type: 'json'` (no other reasonable default for raw arrays).

**S4 — Invalid attribute usage fails fast**
1. Developer puts `#[Field]` on an untyped property → registration throws with a clear error.
2. Developer puts `#[Field]` on a property whose type the framework cannot infer → throws with the offending class/property and a hint to use `#[Field(type: '…')]` explicitly.
3. Developer puts `#[ContentEntityType]` without an `id` → throws.

**S5 — `EntityType::fromClass()` is the only path**
1. Calling `new EntityType(id: …, fieldDefinitions: […])` no longer compiles — the `fieldDefinitions:` parameter is removed from the constructor.
2. Apps that previously passed field definitions inline must migrate to attributes; there is no alternative.

### Edge cases

- E1: A property is declared `private` or `protected`. Decision: `#[Field]` requires `public` typed properties. Anything else throws a clear error.
- E2: Inheritance — a parent class with `#[Field]` properties. The child class inherits them; `EntityType::fromClass(Child::class)` walks the chain (per existing `EntityMetadataReader` pattern).
- E3: A property has both a typed declaration and an attribute-supplied `type:` parameter that disagree (e.g. `public int $x` with `#[Field(type: 'string')]`). Decision: throw — explicit conflict.
- E4: An entity class without any `#[Field]` properties produces an entity type with an empty field definition map. Allowed (some entities may store everything via reserved keys only).

## 3. Functional Requirements

| ID | Requirement | Status |
|---|---|---|
| FR-001 | The framework shall provide a `#[Field]` PHP attribute targeting class properties. | Proposed |
| FR-002 | The `#[Field]` attribute shall accept optional parameters: `type` (string), `required` (bool), `default` (mixed), and any further parameters that mirror the existing `FieldDefinition` shape (final list determined at plan time). | Proposed |
| FR-003 | When `#[Field]` is placed on a public typed property, the framework shall infer the field `type` from the PHP type when `type:` is not explicitly set. The mapping rules shall cover: `string`, `int`, `bool`, `array`, nullable variants of each, `\DateTimeImmutable`, and backed enums. | Proposed |
| FR-004 | When `#[Field]` is placed on a property whose type cannot be inferred and no explicit `type:` is provided, the framework shall throw an exception identifying the class, property, and PHP type. | Proposed |
| FR-005 | The framework shall provide `EntityType::fromClass(string $className): EntityType` which uses reflection to read `#[ContentEntityType]`, `#[ContentEntityKeys]`, and `#[Field]` attributes and returns a fully-populated `EntityType` instance. | Proposed |
| FR-006 | The `#[ContentEntityType]` attribute shall be extended to accept `label` and `description` parameters in addition to `id`. The `id` remains required; `label` defaults to the entity type id; `description` defaults to null. | Proposed |
| FR-007 | The `EntityType` constructor's `fieldDefinitions:` parameter shall be removed. Field definitions are sourced exclusively from `#[Field]` attributes via `EntityType::fromClass()`. | Proposed |
| FR-008 | `EntityTypeManager::assertClassMetadataMatchesEntityType()` shall be removed. With a single source of truth, the validator has no purpose. | Proposed |
| FR-009 | All entity classes inside the framework monorepo (across `packages/*/src/Entity/`, `packages/*/tests/.../Entity/`, in-tree examples) shall be migrated to use `#[Field]` properties; their registrations shall use `EntityType::fromClass()`. | Proposed |
| FR-010 | When a class declares both a typed property and a conflicting `type:` parameter on its `#[Field]` attribute (e.g. `public int $x` with `#[Field(type: 'string')]`), `EntityType::fromClass()` shall throw an exception identifying the class, property, declared property type, and conflicting attribute type. | Proposed |
| FR-011 | `#[Field]` shall be applicable only to `public` properties of a class extending `ContentEntityBase`. Application to non-public properties or to non-entity classes shall throw at `EntityType::fromClass()` time. | Proposed |
| FR-012 | Field definitions inferred from attributes shall be cached per-class (mirroring the existing `EntityMetadataReader::$cache`) so reflection runs at most once per class per process. | Proposed |

## 4. Non-Functional Requirements

| ID | Requirement | Status |
|---|---|---|
| NFR-001 | `EntityType::fromClass()` shall complete in under 5 ms per class on a development machine for a typical entity (≤ 20 fields), including first-call reflection. | Proposed |
| NFR-002 | Subsequent calls to `EntityType::fromClass()` for the same class shall be served from cache and complete in under 0.1 ms. | Proposed |
| NFR-003 | The framework's existing PHPUnit suite shall pass with no decrease in coverage after this mission lands. | Proposed |
| NFR-004 | Errors raised during attribute reading shall include the offending class FQN, property name, and a one-line remediation hint. | Proposed |

## 5. Constraints

| ID | Constraint | Status |
|---|---|---|
| C-001 | Greenfield: there is no backwards-compatibility requirement for the `fieldDefinitions:` array parameter on `EntityType` — it is removed in this mission. | Proposed |
| C-002 | Bundle-scoped fields (`#[Field(bundle: '…')]` semantics) are out of scope; deferred to mission `bundle-scoped-field-attribute`. | Proposed |
| C-003 | Composer-classmap auto-discovery of entity classes is out of scope; deferred to M2 (`attribute-entity-classmap-discovery`). Apps in M1 still call `EntityType::fromClass()` explicitly to register. | Proposed |
| C-004 | Doctrine / DBAL cutover is out of scope; deferred to mission `doctrine-dbal-cutover`. | Proposed |
| C-005 | AI-integration attributes (`#[VectorIndexed]`, etc.) are out of scope; deferred to mission `entity-vector-indexed-attribute`. | Proposed |
| C-006 | The `ContentEntityBase` constructor signature cleanup is out of scope; deferred to mission `entity-base-constructor-cleanup`. | Proposed |
| C-007 | The `#[Field]` attribute lives in `Waaseyaa\Entity\Attribute\Field` (sibling of existing `ContentEntityType`/`ContentEntityKeys`). Final placement confirmed at plan time. | Proposed |

## 6. Success Criteria

- **SC-001** A new entity in any framework package is fully defined in **one place** (its class file). No app-level `config/entity-types.php` entries are needed for the field shape — only an `EntityType::fromClass()` registration call.
- **SC-002** Across the framework monorepo, **zero** entity classes after this mission lands declare field definitions anywhere other than via `#[Field]` attributes.
- **SC-003** `EntityTypeManager::assertClassMetadataMatchesEntityType()` is gone from the codebase (`grep -r assertClassMetadataMatchesEntityType packages/` returns zero hits).
- **SC-004** A developer can write a new entity class, register it with one line of PHP, and have it round-trip through `EntityRepository::save()` / `load()` without touching any other configuration file.
- **SC-005** The framework's PHPUnit suite passes; no test count regression; no coverage regression.

## 7. Key Entities (framework code)

- **`Waaseyaa\Entity\Attribute\Field`** (NEW)
- **`Waaseyaa\Entity\Attribute\ContentEntityType`** (extended: `label`, `description`)
- **`Waaseyaa\Entity\Attribute\EntityMetadataReader`** (extended: `resolveFields()`)
- **`Waaseyaa\Entity\EntityType`** (removes `fieldDefinitions:` constructor param; adds `static fromClass()`)
- **`Waaseyaa\Entity\EntityTypeManager`** (removes `assertClassMetadataMatchesEntityType()`)
- All `Entity` classes across `packages/*/src/Entity/` (gain `#[Field]` annotations)

## 8. Assumptions

- A1: Reflection cost is small enough to not warrant ahead-of-time codegen (NFR-001/NFR-002 enforce this).
- A2: All current entity classes have field types that map cleanly to one of the inferred categories or can declare `type:` explicitly.
- A3: `waaseyaa/typed-data` continues to handle property-level reads/writes; this mission does not relitigate cast/hydration.
- A4: `EntityValuesInterface` and the storage layer consume the same `array<string, FieldDefinitionInterface>` map; only the *source* of that map changes.

## 9. Dependencies

- `Waaseyaa\Entity\ContentEntityBase`, `EntityType`, `EntityTypeManager`, `EntityMetadataReader` (modified).
- `Waaseyaa\Field\FieldDefinitionInterface` (consumed unchanged).
- PHP 8.4 reflection API.

## 10. Out of Scope (tracked as separate missions)

- **`attribute-entity-classmap-discovery`** (M2)
- **`bundle-scoped-field-attribute`**
- **`entity-base-constructor-cleanup`**
- **`entity-vector-indexed-attribute`**
- **`doctrine-dbal-cutover`**
