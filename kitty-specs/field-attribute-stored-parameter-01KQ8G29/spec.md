# Spec — `#[Field(stored:)]` parameter

## Summary

Extend the `#[Field]` PHP attribute (`packages/entity/src/Attribute/Field.php`) with a `stored: FieldStorage` parameter, defaulting to `FieldStorage::Column`. Plumb the value through `EntityMetadataReader::resolveFields()` into the existing `FieldDefinition::__construct(stored: ...)` slot. Migrate `Waaseyaa\Groups\Group` to declare its three universal core fields (`status`, `created_at`, `updated_at`) via attribute-first declaration, and replace `GroupsServiceProvider`'s direct `new EntityType(..., _fieldDefinitions: [...])` workaround with `EntityType::fromClass(Group::class)`.

## User story

> As a Waaseyaa entity author, I want to declare `stored: FieldStorage::Data` directly on a property's `#[Field]` attribute, so I can opt fields into JSON-blob storage without dropping into raw `EntityType` construction or the internal `_fieldDefinitions:` slot.

## Background

M1 (`attribute-first-entity-definition-01KQ6DXE`, merged at `ce123bfe`) shipped attribute-first entity declaration. WP05 surfaced — and `docs/specs/entity-system.md` §"Known Transitional Gaps" item 3 records — that `#[Field]` cannot express `stored: FieldStorage::Data`. `Waaseyaa\Groups\Group` therefore registers via a manual `EntityType` construction inside `GroupsServiceProvider`, bypassing `EntityType::fromClass()`. This mission closes that gap.

## Functional requirements

- **FR-001:** `(new \Waaseyaa\Entity\Attribute\Field())->stored === \Waaseyaa\Field\FieldStorage::Column`.
- **FR-002:** `(new \Waaseyaa\Entity\Attribute\Field(stored: \Waaseyaa\Field\FieldStorage::Data))->stored === \Waaseyaa\Field\FieldStorage::Data`.
- **FR-003:** `EntityMetadataReader::resolveFields()` forwards `$field->stored` into `FieldDefinition::__construct(stored: ...)` for every `#[Field]`-decorated property — verified by a unit test that resolves a fixture class with one `Column` field and one `Data` field.
- **FR-004:** `FieldTypeInferrer::infer()` is unchanged; a regression test confirms its `{type, required, settings}` output is independent of `stored:`.
- **FR-005:** `Waaseyaa\Groups\Group` declares `status`, `created_at`, `updated_at` as public typed `int` properties decorated with `#[Field(stored: \Waaseyaa\Field\FieldStorage::Data)]`.
- **FR-006:** `Waaseyaa\Groups\GroupsServiceProvider` registers `Group` via `$this->entityType(\Waaseyaa\Entity\EntityType::fromClass(\Waaseyaa\Groups\Group::class))` — the workaround comment block (currently lines 24–33) and direct `new EntityType(...)` call (currently lines 34–70) are gone, along with now-unused imports.
- **FR-007:** `./vendor/bin/phpunit packages/groups/tests/` reports 13 passing, 0 failing.
- **FR-008:** `./vendor/bin/phpunit packages/entity/tests/Unit/Attribute/` reports green, including new tests added in this mission.
- **NFR-001:** `./vendor/bin/phpunit` (full suite), `composer phpstan`, `composer cs-check`, and `bin/check-package-layers` all pass.
- **FR-009:** `docs/specs/entity-system.md` §"Known Transitional Gaps" item 3 is marked closed (or removed), referencing this mission's slug.

## Non-goals

- No change to `FieldDefinition`'s existing `stored:` parameter, accessors, or storage-handler behaviour — that machinery already works and is exercised by today's `Group` workaround.
- No migration of other entities. `Node`, `User`, `Note`, etc. continue with their default `FieldStorage::Column` semantics.
- No new field types, no schema-handler changes, no query-routing changes.
- No movement in the layer graph — `entity` and `field` packages are both Layer 1.

## Architectural notes

- **Attribute parameter ordering:** `stored:` is appended as the final constructor parameter on `#[Field]` to keep all existing call sites positionally and named-argument compatible.
- **Single forwarding point:** `EntityMetadataReader::resolveFields()` (`packages/entity/src/Attribute/EntityMetadataReader.php:108-121`) is the only place that needs to pass `$field->stored` through. `FieldTypeInferrer` does not handle storage.
- **`Group` properties:** Match style of `packages/node/src/Node.php` and `packages/user/src/User.php` — public typed properties with no initializer; `ContentEntityBase` populates from the constructor `$values` array.
- **Backwards compatibility:** Defaulting to `FieldStorage::Column` makes this a transparent extension: every existing `#[Field]` in the repo continues to behave exactly as before.

## Dependencies

- M1 (`attribute-first-entity-definition-01KQ6DXE`) merged. ✓
- `Waaseyaa\Field\FieldStorage` enum exists (`packages/field/src/FieldStorage.php`). ✓
- `Waaseyaa\Field\FieldDefinition::__construct(... stored: FieldStorage = FieldStorage::Column ...)` exists (`packages/field/src/FieldDefinition.php:30`). ✓
- `Waaseyaa\Entity\EntityType::fromClass()` exists (`packages/entity/src/EntityType.php:86-128`). ✓

No external blockers.

## Verification commands

```bash
./vendor/bin/phpunit packages/entity/tests/Unit/Attribute/
./vendor/bin/phpunit packages/groups/tests/        # expect 13/13
./vendor/bin/phpunit                                # full suite
composer phpstan
composer cs-check
bin/check-package-layers
bin/waaseyaa optimize:manifest                      # boot smoke
```
