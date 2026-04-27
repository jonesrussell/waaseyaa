# Research — Attribute-First Entity Definition

Resolves the open clarifications surfaced during planning.

---

## R1 — Field-type ID inventory in `waaseyaa/field`

**Decision**: M1 maps inferred PHP types to the existing field-type IDs registered as `#[FieldType]` plugins in `packages/field/src/Item/`. Concrete inventory:

`boolean`, `computed`, `date`, `datetime`, `decimal`, `email`, `entity_reference`, `file`, `float`, `image`, `integer`, `json`, `link`, `list`, `string`, `text`.

**Rationale**: These IDs are the canonical surface; `FieldDefinition::$type` is consumed by storage drivers, schema derivation, and the AI-schema generator via this string. Inventing a parallel taxonomy in the attribute layer would create a second source of truth.

**Alternatives considered**:
- Define a parallel enum in `packages/entity/src/Attribute/FieldKind.php` and translate at construction time. Rejected — duplication.
- Lazy-resolve via plugin discovery (look up by PHP-type → plugin metadata). Rejected for M1 — the plugin discovery layer is its own complexity and the table is small enough to encode statically.

---

## R2 — Symfony Constraint integration via attribute

**Decision**: Out of scope for M1.

**Rationale**: `FieldDefinition::$constraints` is `Constraint[]` — Symfony Validator constraint instances. PHP attributes can only encode scalar values, simple arrays, and class-name string references; passing constructed `Constraint` objects through a `#[Field]` attribute would require either:
1. Encoding constraints as their class name + arg array, then instantiating at reflection time. Doable but adds a small DSL surface that deserves its own design conversation (defaults, custom constraints, validation groups).
2. A separate `#[Validate(...)]` attribute layered on top. Cleaner separation of concerns.

Both approaches benefit from being designed alongside the field-level constraints story rather than smuggled into M1. The follow-up can be its own short mission.

**M1 fallback**: `#[Field]` accepts a `settings:` array; teams that need a quick constraint can stash a serializable hint there and validate manually at storage time. Not pretty, but available.

**Alternatives considered**:
- Ship constraint encoding now. Rejected — design cost > M1 scope budget.

---

## R3 — Backed enum handling

**Decision**: M1 maps a `BackedEnum` property type to field-type-id `'string'`, with `settings: ['enum_class' => MyEnum::class]` recorded for downstream consumers (validator, schema generator, hydration cast). A dedicated `'enum'` field-type plugin is **not** introduced in M1; it becomes a follow-up.

**Rationale**:
- `waaseyaa/field` registers no `'enum'` type today.
- Adding an `'enum'` field-type plugin is a meaningful design exercise: how does the validator enforce values? how does the schema generator render JSON Schema? how does AI-schema describe the enum? These are good questions but each has multi-package ramifications.
- The fallback (`'string'` + `settings.enum_class`) is enough to round-trip values via cast logic and is consistent with the existing typed-data hydration pattern.

**Alternatives considered**:
- Introduce `'enum'` field type now. Rejected — out of M1 budget.
- Throw at attribute-read time on enum properties, demanding explicit `#[Field(type: '…')]`. Rejected — backed enums are common; throwing makes the attribute feel hostile to typed code.

---

## R4 — Multi-value (cardinality) via property attributes

**Decision**: M1 produces `cardinality: 1` for every `#[Field]`-decorated property. `array` typed properties become `'json'` (single-value, JSON-encoded blob). Multi-value semantics via property attributes are deferred.

**Rationale**: PHP property declarations cannot carry generic-type information (`array<string>` is just `array` at the type level). Honest multi-value support requires either:
- A `cardinality:` parameter on `#[Field]` plus an `itemType:` parameter, OR
- A separate `#[FieldList]` attribute, OR
- A dedicated multi-value property type wrapper.

All viable, none clearly best without a real use case to anchor the design. Deferred to a follow-up mission. Apps that need multi-value today continue to use `cardinality: -1` via direct `FieldDefinition` construction in the (now internal) test helper or via a separate `EntityType` instance for tests.

---

## R5 — Test migration patterns

**Decision**: Two patterns, applied per call site:

1. **Real fixture class** (preferred). For tests that exercise behavior of an entity type, define a small attribute-decorated test entity in `packages/<pkg>/tests/Fixtures/AttributeFirstEntities/`. Examples:
   ```php
   #[ContentEntityType(id: 'test_post', label: 'Test Post')]
   final class TestPost extends ContentEntityBase {
       #[Field] public string $title;
       #[Field] public ?string $body;
   }
   ```
   Then `EntityType::fromClass(TestPost::class)` in the test setup.

2. **`TestEntityType::stub()` helper** (escape hatch). For tests that intentionally probe `EntityType` shape independent of any class — e.g., schema-controller tests that pass synthetic field definitions to verify rendering. Helper signature:
   ```php
   final class TestEntityType {
       public static function stub(string $id, array $rawFieldDefinitions): EntityType { ... }
   }
   ```
   Internally uses the same constructor path as `fromClass()` but skips reflection. Lives in `packages/entity/tests/Helper/`.

**Rationale**: Most fixture sites today use `fieldDefinitions: [...]` to express what an entity *would* look like rather than as a stand-in for class-level metadata. The first pattern matches the new public API; the second covers cases where reflecting from a class is itself the unit under test (e.g., the inference logic).

---

## R6 — `EntityType` constructor visibility post-removal

**Decision**: The `EntityType` constructor stays public (the class is `final readonly` and removing the public constructor breaks too much), but `fieldDefinitions:` is removed entirely. Direct construction is still useful for:
- Config entity types (no `#[Field]` reflection — they're typed differently).
- Tests via `TestEntityType::stub()`.

`fromClass()` is the **only** way to construct an `EntityType` for a content entity type from the public API perspective — enforced by convention and by the `fromClass()` having all the type-resolution logic concentrated.

---

## R7 — Inheritance handling

**Decision**: `EntityMetadataReader::resolveFields()` walks the class hierarchy from the first concrete subclass of `ContentEntityBase` down to the target class, in the same way `resolveKeys()` already does. Child-class `#[Field]` attributes override parent-class ones with the same name (last-wins).

**Rationale**: Mirrors existing `ContentEntityKeys` resolution. Tested explicitly in WP03.

---

## R8 — Caching semantics

**Decision**: Per-class cache via the existing `EntityMetadataReader::$cache` static array, extended to include the resolved field map. `EntityMetadataReader::clearCacheForClass()` and `clearCache()` already exist for test isolation; both are extended to clear the field cache too.

**Rationale**: Single cache layer; matches existing pattern; satisfies NFR-002 (sub-0.1ms cached).
