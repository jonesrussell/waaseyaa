# Research — `#[Field(stored:)]` parameter

## Mission

Extend the `#[Field]` PHP attribute (`packages/entity/src/Attribute/Field.php`) to expose a `stored: FieldStorage` parameter, defaulting to `FieldStorage::Column`. This closes Known Transitional Gap #3 from `docs/specs/entity-system.md`, which was surfaced concretely by WP05 of M1 (`attribute-first-entity-definition-01KQ6DXE`, merged at `ce123bfe`).

## Decisions

### D1 — `stored:` is the last constructor parameter on `#[Field]`
**Decision:** Add `public FieldStorage $stored = FieldStorage::Column` as the final constructor parameter, after `revisionable`.
**Rationale:** Append-only positions preserve all existing call sites. `FieldStorage` is already an existing enum at `packages/field/src/FieldStorage.php` with values `Column` (per-column SQL) and `Data` (JSON `_data` blob). Default `Column` preserves the pre-attribute semantics.
**Evidence:** `packages/entity/src/Attribute/Field.php:34-44`; `packages/field/src/FieldStorage.php`.

### D2 — `EntityMetadataReader::resolveFields()` is the single forwarding point
**Decision:** Pass `$field->stored` into the existing `FieldDefinition::__construct(stored: ...)` parameter at `packages/entity/src/Attribute/EntityMetadataReader.php:108-121`.
**Rationale:** `FieldDefinition` already accepts `stored: FieldStorage = FieldStorage::Column` (`packages/field/src/FieldDefinition.php:30`). The reader is the only data-flow path from `#[Field]` into `FieldDefinition`. No other plumbing is required.
**Evidence:** `FieldDefinition.php:15-34` (constructor).

### D3 — `FieldTypeInferrer::infer()` is NOT modified
**Decision:** Leave `FieldTypeInferrer::infer()` unchanged. Add a regression unit test that locks in independence of `stored:` and inferred `{type, required, settings}`.
**Rationale:** The inferrer's contract is to derive type/required/settings from the PHP property type and explicit overrides. `stored:` is a storage hint orthogonal to type inference; routing it through the inferrer would store-and-discard. The user-facing task brief listed it for completeness, but exploration confirms it has no role in the data path.
**Evidence:** `packages/entity/src/Attribute/FieldTypeInferrer.php:71-124` returns only `array{type, required, settings}`.

### D4 — Migrate `Group` to attribute-first; keep `EntityType::fromClass()` as the canonical path
**Decision:** Add three public typed properties on `Waaseyaa\Groups\Group` (`status`, `created_at`, `updated_at`) decorated with `#[Field(stored: FieldStorage::Data)]`, and collapse `GroupsServiceProvider`'s direct `new EntityType(..., _fieldDefinitions: [...])` call to `$this->entityType(EntityType::fromClass(Group::class))`.
**Rationale:** `_fieldDefinitions:` is documented as an internal slot for transitional cases. Once `#[Field(stored:)]` exists, the workaround is unnecessary and `Group` aligns with `Node`/`User` style.
**Evidence:** `packages/groups/src/GroupsServiceProvider.php:24-70`; `packages/node/src/Node.php:34-59`; `packages/user/src/User.php:39-48`.

### D5 — Layer compatibility is unchanged
**Decision:** No layer-graph movement. `packages/entity` (L1) imports `Waaseyaa\Field\FieldStorage` from `packages/field` (L1).
**Rationale:** Same-layer imports are permitted. `bin/check-package-layers` continues to pass.

## Non-decisions / out of scope

- No change to `FieldDefinition`'s existing `stored:` parameter, accessor, or storage-handler behaviour.
- No migration of other entities (Node, User, Note, etc. remain on `Column`).
- No new field types, no schema-handler changes, no query-routing changes — those exist already and are exercised by the workaround Group uses today.

## Open questions / risks

- **Risk (low):** A property typed `int` with `stored: FieldStorage::Data` must not break query routing. Mitigation: existing `Group` workaround already exercises this path; `groups/tests/` suite (currently 13/13) is the regression gate.
- **Question:** Should the gap-closure entry in `docs/specs/entity-system.md` be deleted or marked "(closed in mission `field-attribute-stored-parameter-01KQ8G29`)"? Default: mark closed for traceability.

## References

- `packages/entity/src/Attribute/Field.php` — attribute definition.
- `packages/entity/src/Attribute/EntityMetadataReader.php:71-126` — `resolveFields()`.
- `packages/entity/src/Attribute/FieldTypeInferrer.php:71-124` — inferrer (untouched).
- `packages/field/src/FieldDefinition.php:15-34` — constructor (already accepts `stored:`).
- `packages/field/src/FieldStorage.php` — enum.
- `packages/entity/src/EntityType.php:86-128` — `EntityType::fromClass()`.
- `packages/groups/src/Group.php` — migration target.
- `packages/groups/src/GroupsServiceProvider.php:24-70` — workaround to remove.
- `docs/specs/entity-system.md` — Known Transitional Gaps §3.
- M1: `kitty-specs/attribute-first-entity-definition-01KQ6DXE/`; merge commit `ce123bfe`.
