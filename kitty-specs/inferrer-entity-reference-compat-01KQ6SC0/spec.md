# Mission Specification: Inferrer entity_reference compat

**Mission ID**: 01KQ6SC0W3PQQ0BYBYRKBEKWTX
**Mission slug**: inferrer-entity-reference-compat-01KQ6SC0
**Mission type**: software-dev
**Target branch**: main
**Created**: 2026-04-27

## Background

The M1 *attribute-first entity definition* mission (merge commit `ce123bfe`) made `#[Field]` the canonical way to declare entity field metadata, with `FieldTypeInferrer` reconciling PHP property types against any explicit `type:` override. M1 deliberately deferred one rough edge: scalar PHP types (`?int`, `?string`) cannot be overridden to `entity_reference`, because the inferrer's compatibility table has no group covering that combination. Properties storing FK ids must therefore use untyped declarations + `@var int|null` PHPDoc, which loses static-type safety and confuses readers.

Documented as transitional gap #3 in `docs/specs/entity-system.md` §"Known Transitional Gaps", with this mission named as the closer.

## Stakeholders

- **Framework engineers** (primary): people writing entity classes inside `waaseyaa/*` packages.
- **Downstream app authors** (secondary): consumers who declare their own entities using `#[Field]`.
- **Static analysis** (tooling): PHPStan, the Inferrer's own `compatibilityGroups()` public seam.

## User Scenarios

### S1 — Declare a typed FK field

An entity author writes:
```php
#[Field(type: 'entity_reference', settings: ['target_entity_type_id' => 'user'])]
public ?int $author_id = null;
```
The inferrer accepts this combination and emits `{type: 'entity_reference', required: false, settings: ['target_entity_type_id' => 'user']}`. Today this raises `EntityMetadataException`.

### S2 — Declare a UUID-keyed FK field

An author whose target entity uses UUIDs writes `public ?string $author_uuid = null;` with the same `entity_reference` override. The inferrer accepts it.

### S3 — Reject a meaningless override

An author who writes `public bool $flag = false;` with `#[Field(type: 'entity_reference', ...)]` still receives the existing `EntityMetadataException` conflict diagnostic — `bool` cannot store an FK.

### S4 — Refactor M1 workaround sites

`Node.uid` and `Term.parent_id` migrate from untyped + `@var int|null` PHPDoc to typed `public ?int $uid = null;` / `public ?int $parent_id = null;`. Behaviour and persisted shape are unchanged.

### S5 — Spec drift closed

`docs/specs/entity-system.md` §"Known Transitional Gaps" item 3 is updated to mark the gap closed by this mission, matching the just-closed `stored:` bullet style on line 589.

## Requirements

### Functional Requirements

| ID | Requirement | Status |
|---|---|---|
| FR-001 | `FieldTypeInferrer::infer()` MUST accept `int` PHP type with explicit `type: 'entity_reference'` and return that type unchanged. | proposed |
| FR-002 | `FieldTypeInferrer::infer()` MUST accept `?int` PHP type with explicit `type: 'entity_reference'` and propagate `required: false` from nullability. | proposed |
| FR-003 | `FieldTypeInferrer::infer()` MUST accept `string` and `?string` PHP types with explicit `type: 'entity_reference'`. | proposed |
| FR-004 | `FieldTypeInferrer::infer()` MUST continue to reject any other PHP scalar (`bool`, `float`, `array`, `\DateTimeImmutable`, backed enum) overridden to `entity_reference`, raising the existing conflict `EntityMetadataException`. | proposed |
| FR-005 | `Node.uid` MUST be declared as `public ?int $uid = null;` with the existing `#[Field(type: 'entity_reference', ...)]` attribute and **without** the `@var int|null` PHPDoc workaround. | proposed |
| FR-006 | `Term.parent_id` MUST be declared as `public ?int $parent_id = null;` with the existing `#[Field(type: 'entity_reference', ...)]` attribute and **without** the `@var int|null` PHPDoc workaround. | proposed |
| FR-007 | `docs/specs/entity-system.md` §"Known Transitional Gaps" item 3 MUST be updated to mark the entity_reference-on-scalar bullet closed by this mission. | proposed |

### Non-Functional Requirements

| ID | Requirement | Status |
|---|---|---|
| NFR-001 | The full PHPUnit suite (`./vendor/bin/phpunit`) MUST pass with zero new failures. | proposed |
| NFR-002 | `composer phpstan` (level 5) MUST report zero new findings; in particular, removing `@var int|null` from `Node.uid` and `Term.parent_id` MUST NOT introduce type-inference regressions. | proposed |
| NFR-003 | `composer cs-check` MUST pass cleanly. | proposed |
| NFR-004 | New test cases in `FieldTypeInferrerTest` MUST cover the four happy-path combinations (`int`, `?int`, `string`, `?string` × `entity_reference`) plus at least one rejection case (e.g., `bool`). | proposed |

### Constraints

| ID | Constraint | Status |
|---|---|---|
| C-001 | The `compatibilityGroups()` public seam MUST remain unchanged in behaviour and contract — its meaning is "symmetric override groups", and `entity_reference` is asymmetric (override-only, never inferred from a scalar). | proposed |
| C-002 | The `target_entity_type_id` settings key (currently in use at `Node.uid`, `Term.parent_id`, and read by `EntityTypeBuilder`) MUST be preserved. The mission brief mentioned `target_type` but renaming would silently break reference resolution; the existing key is canonical. | proposed |
| C-003 | No persisted column shape, migration, JSON:API serialization, or GraphQL field name MUST change as a result of this mission. | proposed |
| C-004 | `entity_reference` MUST NOT be added as an inferred field-type for any PHP scalar. The new compatibility rule is one-way (scalar → entity_reference via explicit override only). | proposed |

## Success Criteria

| ID | Criterion |
|---|---|
| SC-001 | An entity author can declare `public ?int $foo = null;` with `#[Field(type: 'entity_reference', settings: ['target_entity_type_id' => 'bar'])]` and the inferrer accepts it without modification. |
| SC-002 | `Node` and `Term` ship without `@var int|null` PHPDoc on their FK properties; reading `$node->getAuthorId()` and `$term->getParentId()` continues to return `int` (or `0` for null) at runtime. |
| SC-003 | `docs/specs/entity-system.md` §"Known Transitional Gaps" item 3 cites this mission slug as the closer of the entity_reference-on-scalar bullet. |
| SC-004 | Full PHPUnit suite, PHPStan, and cs-check are green. |

## Key Entities (metadata only — no storage change)

- **`FieldTypeInferrer`** (`packages/entity/src/Attribute/FieldTypeInferrer.php`): gains a private constant `ENTITY_REFERENCE_COMPATIBLE_INFERRED` and one branch in `isCompatible()`.
- **`Node.uid`** (`packages/node/src/Node.php`): property declaration refined to `?int`; storage and behaviour unchanged.
- **`Term.parent_id`** (`packages/taxonomy/src/Term.php`): property declaration refined to `?int`; storage and behaviour unchanged.

## Out of Scope

- Other untyped FK properties not enumerated above.
- Timestamp fallback fields (gap #2 — separate mission `field-type-timestamp-plugin-01KQ7JHN`).
- Inferring `entity_reference` from a PHP scalar without an explicit `type:` override.
- Mutating the symmetric `compatibilityGroups()` public seam.
- Renaming the `target_entity_type_id` settings key.

## Assumptions

- Downstream callers of `Node.uid` and `Term.parent_id` already treat the value as nullable int; the existing PHPDoc guarded that contract. Stricter PHP typing should not break correct callers and will surface incorrect ones.
- No app outside `waaseyaa/*` currently depends on the inferrer rejecting `?int` → `entity_reference`; the existing rejection has no documented use.

## Edge Cases

- **Backed enums** with `int` backing remain on the `enum` path (handled before compatibility check); no interaction with the new rule.
- **Union/intersection/untyped** properties continue to require explicit `type:` and are unaffected.
- **`required` propagation**: `?int + entity_reference` yields `required: false` (nullable), `int + entity_reference` yields `required: true`. `Field::required` continues to override.
