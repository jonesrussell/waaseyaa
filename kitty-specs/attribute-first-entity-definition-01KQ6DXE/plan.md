# Implementation Plan: Attribute-First Entity Definition

**Mission**: `attribute-first-entity-definition-01KQ6DXE`
**Mission ID**: `01KQ6DXEQ01S6PVPT6KF5946TA`
**Branch contract**: `main` → `main` (matches target)
**Spec**: [`spec.md`](./spec.md)
**Created**: 2026-04-27

---

## Technical Context

| Aspect | Value |
|---|---|
| Language / runtime | PHP 8.4 (framework requires `>=8.4`). |
| Reflection | PHP 8.4 `ReflectionClass`/`ReflectionProperty`/`ReflectionAttribute`. |
| Existing infrastructure | `Waaseyaa\Entity\Attribute\ContentEntityType`, `ContentEntityKeys`, `EntityMetadataReader` (with per-class cache); `Waaseyaa\Field\FieldDefinition` (final readonly target shape). |
| Field-type registry | 16 field type IDs registered via `waaseyaa/field` `#[FieldType]` plugins: `boolean`, `computed`, `date`, `datetime`, `decimal`, `email`, `entity_reference`, `file`, `float`, `image`, `integer`, `json`, `link`, `list`, `string`, `text`. **No `enum`.** Backed enums map to `'string'` in M1; proper enum field type is a follow-up. |
| Existing entity classes (production) | 5: `packages/genealogy/src/Entity/{GenealogyEvent,GenealogyFamily,GenealogyPerson,GenealogyTree}.php`, `packages/oidc/src/Entity/OidcClient.php`. |
| Existing `fieldDefinitions:` call sites | ~45 (most in test fixtures). All migrate in M1 per A choice. |
| Backwards compatibility | None required — greenfield framework, alpha. The `fieldDefinitions:` parameter on `EntityType` is removed outright. |
| Charter Check | Skipped — no `.kittify/charter/charter.md` file. |

## Constitution / Gate Checks

| Gate | Status | Notes |
|---|---|---|
| Branch matches target | ✅ | `main` → `main`. |
| All FRs have testable acceptance criteria | ✅ | FR-001 through FR-012 each map to a unit test or migration check. |
| Greenfield: no deprecated path retained | ✅ | C-001. The `fieldDefinitions:` constructor param is removed, not deprecated. |
| Bulk-edit gate | N/A | Mission is not a string-rename; each call-site migration is semantic, not pattern-substitution. `change_mode` remains default. |
| Performance budget | ✅ | NFR-001 (5 ms first reflection), NFR-002 (0.1 ms cached). |
| Test suite green requirement | ✅ | NFR-003. WP08 enforces. |

## Architecture Decisions

### AD-1: New attribute lives in `Waaseyaa\Entity\Attribute\Field`

`packages/entity/src/Attribute/Field.php`. Sibling of existing `ContentEntityType`/`ContentEntityKeys`. Not `Waaseyaa\Field\Attribute\Field` because:
- The `waaseyaa/field` package owns field-type **plugins** (e.g. `StringItem`, `DateItem` decorated with `#[FieldType]`); placing a property-targeting `#[Field]` attribute alongside `#[FieldType]` would conflate two distinct concerns (declaring a field-type plugin vs marking a property as a fielded value).
- The `waaseyaa/entity` package owns the entity-level metadata story; `#[Field]` is part of that story.

### AD-2: Type inference logic lives in `EntityMetadataReader`

Existing class already does per-class reflection caching for `ContentEntityType` and `ContentEntityKeys`. Adding `resolveFields(string $class): array<string, FieldDefinition>` here keeps all attribute-reading logic in one place.

### AD-3: `EntityType::fromClass()` is the only path; constructor remains technically callable

`EntityType` is `final readonly`. Removing the `fieldDefinitions:` parameter is a true API break; consumers must use `fromClass()`. Direct `new EntityType(...)` construction stays possible (for non-content entity types like config entities, where field-attribute reflection doesn't apply), but for content entities, `fromClass()` is mandatory by convention.

### AD-4: PHP-type → field-type-id mapping (concrete table)

| PHP type | Field type ID | `required` derivation |
|---|---|---|
| `string` | `string` | nullable → false; non-nullable → true |
| `?string` | `string` | false |
| `int` | `integer` | nullable → false; non-nullable → true |
| `?int` | `integer` | false |
| `bool` | `boolean` | nullable → false; non-nullable → true |
| `?bool` | `boolean` | false |
| `float` | `float` | nullable → false; non-nullable → true |
| `?float` | `float` | false |
| `array` | `json` | non-nullable → true; treated as scalar (cardinality 1, JSON-encoded) |
| `?array` | `json` | false |
| `\DateTimeImmutable` | `datetime` | non-nullable → true |
| `?\DateTimeImmutable` | `datetime` | false |
| Backed enum class | `string` | true unless nullable, with `settings: ['enum_class' => …]` recorded |
| Anything else | (throws) | — |

Properties without explicit type or with unsupported type force the developer to use `#[Field(type: '…')]` explicitly; otherwise `EntityType::fromClass()` throws.

### AD-5: Cardinality 1 only in M1

`#[Field]` always produces `cardinality: 1`. Multi-value via property attributes is deferred (see open-question follow-ups in [research.md](./research.md)). `array`/`iterable` typed properties map to `'json'`, not to multi-value `'string'` lists.

### AD-6: Test fixtures use real attribute-decorated classes; raw schema tests use a small helper

`packages/entity/tests/Helper/TestEntityType.php` (new) provides `TestEntityType::stub(string $id, array $rawFieldDefs): EntityType` for tests that intentionally exercise `EntityType` shape independent of any PHP class. Most tests get migrated to use real attribute-decorated test entities.

## Files to Create / Modify

### New files

- `packages/entity/src/Attribute/Field.php` — the attribute class.
- `packages/entity/src/Attribute/FieldTypeInferrer.php` — pure helper: `infer(\ReflectionProperty $p, Field $attr): array{type:string, required:bool, settings:array}`. Keeps the mapping table out of `EntityMetadataReader`.
- `packages/entity/tests/Helper/TestEntityType.php` — minimal test-only factory.
- `packages/entity/tests/Unit/Attribute/FieldAttributeTest.php` — attribute tests.
- `packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php` — inference table tests.
- `packages/entity/tests/Unit/EntityTypeFromClassTest.php` — `fromClass()` integration tests.
- `packages/entity/tests/Fixtures/AttributeFirstEntities/*.php` — small attribute-decorated test entity classes.

### Modified files

- `packages/entity/src/Attribute/ContentEntityType.php` — add `label`, `description` parameters with sensible defaults.
- `packages/entity/src/Attribute/EntityMetadataReader.php` — add `resolveFields(string $class): array<string, FieldDefinition>`. Extend `EntityClassMetadata` to carry resolved fields (or add a separate cache slot).
- `packages/entity/src/Attribute/EntityClassMetadata.php` — extend with `?array $fields` slot.
- `packages/entity/src/EntityType.php` — drop `fieldDefinitions:` constructor param; add `static fromClass(string $class): self` factory.
- `packages/entity/src/EntityTypeManager.php` — delete `assertClassMetadataMatchesEntityType()` and the call to it from `registerEntityType()`.
- `packages/entity/src/ContentEntityBase.php` — no signature change in this mission (per C-006); the constructor's existing fallback to `EntityMetadataReader::forClass()` continues to work.
- `packages/genealogy/src/Entity/{GenealogyEvent,GenealogyFamily,GenealogyPerson,GenealogyTree}.php` — add `#[Field]` to typed properties; drop boilerplate constructor overrides.
- `packages/oidc/src/Entity/OidcClient.php` — same.
- All registrations of these entity types (search `EntityType\(.*GenealogyEvent::class` etc.) — switch to `EntityType::fromClass(GenealogyEvent::class)`.
- ~40 test files containing `fieldDefinitions:` calls — migrate to attribute-decorated test entities or `TestEntityType::stub()`.
- `docs/specs/entity-system.md` — update §EntityType Definition and §Public Surface to describe the new flow. Mark old `EntityType(fieldDefinitions: ...)` example as historical, replace with attribute example.
- Any PHPStan baseline entries referencing the removed parameter.

## Phase 0 — Research

See [`research.md`](./research.md). Resolves:
- Field-type ID inventory (done — table in AD-4).
- Symfony Constraint integration (deferred to follow-up).
- Backed-enum handling (M1: map to `string` + settings; full enum field-type is a separate mission).
- Multi-value cardinality via attributes (deferred).
- Test migration patterns (use real fixture classes; `TestEntityType::stub()` for raw shape tests).

## Phase 1 — Design & Contracts

- [`data-model.md`](./data-model.md) — describes the new `#[Field]` attribute and extended `#[ContentEntityType]` as the "data model" of this framework feature.
- [`contracts/php-api.md`](./contracts/php-api.md) — public PHP API surface contract: attribute classes, `EntityType::fromClass()` factory signature, error semantics.
- [`quickstart.md`](./quickstart.md) — end-to-end example of defining a new content entity post-M1.

## Verification

End-to-end checks (post-merge):

1. `vendor/bin/phpunit` in framework repo: green; no test count regression.
2. `grep -rn "fieldDefinitions:" packages/ | wc -l` returns **0** for production code paths and **0** for tests (or `TestEntityType::stub` calls only — no inline `EntityType(fieldDefinitions: …)`).
3. `grep -rn "assertClassMetadataMatchesEntityType" packages/` returns **0** matches.
4. `vendor/bin/phpstan analyse` passes; baseline updated if needed.
5. New benchmark test asserts `EntityType::fromClass(GenealogyPerson::class)` < 5 ms first call, < 0.1 ms cached (NFR-001/002).
6. Course-journey app's path-repo'd consumption: `composer install` resolves clean; existing `course_assignment` etc. continue to register (course-journey adopts attribute migration in a follow-up after this mission lands and a new alpha is cut, OR via the path-repo immediately).
