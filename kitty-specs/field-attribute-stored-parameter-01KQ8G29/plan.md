# Implementation Plan: `#[Field(stored:)]` parameter

**Branch**: `main` (small change; landed directly on main per project workflow)
**Date**: 2026-04-27
**Spec**: [spec.md](./spec.md)
**Research**: [research.md](./research.md)
**Data model**: [data-model.md](./data-model.md)

## Summary

Add `public FieldStorage $stored = FieldStorage::Column` as the final constructor parameter on `#[Field]` (`packages/entity/src/Attribute/Field.php`). Forward `$field->stored` from `EntityMetadataReader::resolveFields()` into `FieldDefinition::__construct(stored: ...)`. Migrate `Waaseyaa\Groups\Group` to declare `status`, `created_at`, `updated_at` as `#[Field(stored: FieldStorage::Data)]` properties. Collapse `GroupsServiceProvider` to `EntityType::fromClass(Group::class)`. Close the gap entry in `docs/specs/entity-system.md`.

## Technical Context

- **Language/Version:** PHP 8.4+, `declare(strict_types=1)` everywhere.
- **Primary dependencies:** Symfony 7.x components; PHPUnit 10.5; Doctrine DBAL.
- **Storage:** No on-disk schema change. `Group` continues to persist `status`/`created_at`/`updated_at` in the bundle-partitioned `_data` JSON blob.
- **Testing:** PHPUnit unit tests in `packages/entity/tests/Unit/` and `packages/groups/tests/`.
- **Target Platform:** Same as repo — PHP runtime, web + CLI.
- **Project type:** Monorepo PHP package.
- **Performance:** N/A — pure metadata path.
- **Constraints:** Backwards compatibility — every existing `#[Field]` call site must continue to work without source changes.
- **Scale:** Affects 1 attribute, 1 reader, 2 entity-package files, plus 1 doc, plus 3 test files.

## Charter Check

- **DIR-001 / DIR-002 / DIR-003** (mission charter directives) — read at start of each WP via `spec-kitty agent context resolve --action implement`.
- **Layer rule:** `entity` and `field` are both Layer 1; same-layer cross-import is permitted. `bin/check-package-layers` is the gate.
- **Architectural quality over backward compatibility (user feedback memory):** No `@deprecated` shims, no `Legacy*` classes; remove the `_fieldDefinitions:` workaround outright in WP02 — callers get updated in the same change.
- **Composer policy:** No new packages, no new path repositories. `bin/check-composer-policy` should remain green.

## Phase 0 — Research

Captured in [research.md](./research.md). Five decisions (D1–D5):
1. `stored:` is appended last on `#[Field]`.
2. `EntityMetadataReader::resolveFields()` is the single forwarding point.
3. `FieldTypeInferrer::infer()` is unchanged (regression test only).
4. Migrate `Group` to attribute-first; keep `EntityType::fromClass()` as canonical.
5. No layer-graph movement.

## Phase 1 — Design

### File map (changes)

| File | Change |
|---|---|
| `packages/entity/src/Attribute/Field.php` | Add `public FieldStorage $stored = FieldStorage::Column` last parameter; add `use Waaseyaa\Field\FieldStorage;`; update docblock. |
| `packages/entity/src/Attribute/EntityMetadataReader.php` | At lines 108–121, append `stored: $field->stored,` to the `FieldDefinition` constructor call. |
| `packages/entity/tests/Unit/Attribute/FieldAttributeTest.php` | Update `it_constructs_with_default_values` to assert `stored === Column`; add `it_accepts_stored_data` test. |
| `packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php` | Add one regression case asserting `stored:` does not change inferred `{type, required, settings}`. |
| `packages/entity/tests/Unit/Attribute/EntityMetadataReaderTest.php` (or new file) | Add a fixture class with one `Column` field and one `Data` field; assert `resolveFields()` returns FieldDefinitions whose storage matches. |
| `packages/groups/src/Group.php` | Add three public typed `int` properties decorated with `#[Field(stored: FieldStorage::Data)]`. |
| `packages/groups/src/GroupsServiceProvider.php` | Replace lines 24–70 with `$this->entityType(EntityType::fromClass(Group::class));`; drop unused imports (`FieldDefinition`, `FieldStorage`, comment block). |
| `docs/specs/entity-system.md` | Mark Known Transitional Gaps §3 closed (referencing mission slug `field-attribute-stored-parameter-01KQ8G29`). |

### Key design decisions

- **Append-only attribute parameter.** `stored:` goes after `revisionable` to avoid touching any current call site. Named-argument and positional callers both stay valid.
- **No inferrer changes.** A regression test makes the asymmetry explicit and prevents drift.
- **Group stays a `final class`.** Only public typed properties are added — same shape as `Node` and `User`. `ContentEntityBase` populates from `$values` at construction.
- **No re-introduction of the `_fieldDefinitions:` slot.** Once WP02 lands, no first-party caller relies on it; if a future need arises it remains available, but Group is no longer the proof case.

## Phase 2 — Tasks (preview)

Driven by `/spec-kitty.tasks` in the next phase. Expected WP graph:

```
WP01 (attribute + reader + tests)
   └─→ WP02 (Group migration + ServiceProvider cleanup)
          └─→ WP03 (close gap entry in entity-system.md)
```

## Verification (acceptance gate)

```bash
./vendor/bin/phpunit packages/entity/tests/Unit/Attribute/
./vendor/bin/phpunit packages/groups/tests/        # 13/13 expected
./vendor/bin/phpunit                                # full suite green
composer phpstan
composer cs-check
bin/check-package-layers
bin/check-composer-policy
bin/waaseyaa optimize:manifest                      # boot smoke
```

End-to-end: `Group` registration via `EntityType::fromClass()` produces an entity type whose three transitional fields resolve through `json_extract` against the `_data` blob — same behavior as today, but driven entirely from class metadata.

## Risks

- **Low — Group test drift.** Mitigated by re-running `packages/groups/tests/` after each WP and treating any deviation from 13/13 as a fail.
- **Low — Reader test coverage gap.** Mitigated by adding a fixture-based test asserting end-to-end forwarding (attribute → reader → FieldDefinition).
- **Low — phpstan signature change on `Field::__construct`.** New required-shape parameter has a default; signatures stay PHPStan-clean.

## Out of scope

- Migrating other entities to `FieldStorage::Data`.
- Changes to `FieldDefinition`, schema handlers, or query routing.
- Layer-graph movement.
- Any new field types or attributes.
