---
work_package_id: WP01
title: 'Add stored: parameter to #[Field] and forward via reader'
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-008
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks: []
history: []
authoritative_surface: packages/entity/
execution_mode: code_change
owned_files:
- packages/entity/src/Attribute/Field.php
- packages/entity/src/Attribute/EntityMetadataReader.php
- packages/entity/tests/Unit/Attribute/FieldAttributeTest.php
- packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php
- packages/entity/tests/Unit/Attribute/EntityMetadataReaderTest.php
tags: []
---

# WP01 — Add `stored:` parameter to `#[Field]` and forward via reader

## Goal

Expose `stored: FieldStorage` on the `#[Field]` attribute and forward it to `FieldDefinition` via `EntityMetadataReader::resolveFields()`. `FieldDefinition` already accepts `stored:` (default `FieldStorage::Column`); the only new wiring is in the attribute and the reader.

## Context

- `packages/entity/src/Attribute/Field.php` — current constructor at lines 34–44. Add `public FieldStorage $stored = FieldStorage::Column` as the **last** parameter (after `revisionable`).
- `packages/entity/src/Attribute/EntityMetadataReader.php:108–121` — current `FieldDefinition` constructor call. Append `stored: $field->stored,`.
- `packages/field/src/FieldDefinition.php:30` — already has `stored: FieldStorage = FieldStorage::Column`.
- `packages/field/src/FieldStorage.php` — enum, cases `Column` and `Data`.
- `packages/entity/src/Attribute/FieldTypeInferrer.php:71–124` — **do not modify**. Add a regression test only.

`packages/entity` and `packages/field` are both Layer 1 — same-layer cross-import is permitted.

## Acceptance criteria (from spec)

- FR-001: `(new Field())->stored === FieldStorage::Column`.
- FR-002: `(new Field(stored: FieldStorage::Data))->stored === FieldStorage::Data`.
- FR-003: `EntityMetadataReader::resolveFields()` forwards `$field->stored` into `FieldDefinition::__construct(stored: ...)`.
- FR-004: `FieldTypeInferrer::infer()` is unchanged; regression test confirms `stored:` does not affect inferred `{type, required, settings}`.
- FR-008: `./vendor/bin/phpunit packages/entity/tests/Unit/Attribute/` is green.

## Subtasks

- [ ] T001 — Add `use Waaseyaa\Field\FieldStorage;` and `public FieldStorage $stored = FieldStorage::Column` parameter to `Field.php` (last position).
- [ ] T002 — Update `Field.php` docblock to mention `stored:` and reference `FieldStorage`.
- [ ] T003 — In `EntityMetadataReader::resolveFields()` (lines 108–121), append `stored: $field->stored,` to the `FieldDefinition` constructor call.
- [ ] T004 — Update `FieldAttributeTest::it_constructs_with_default_values()` to also assert `stored === FieldStorage::Column`.
- [ ] T005 — Add `FieldAttributeTest::it_accepts_stored_data()` asserting `stored === FieldStorage::Data` when passed.
- [ ] T006 — Add a `FieldTypeInferrerTest` regression case (data-provider entry or dedicated test) asserting `stored:` does not change the inferred triple.
- [ ] T007 — Add `EntityMetadataReaderTest` case with a fixture class carrying one `Column` field and one `Data` field; assert each resolved `FieldDefinition`'s storage matches.
- [ ] T008 — Run `./vendor/bin/phpunit packages/entity/tests/Unit/Attribute/`, `composer phpstan`, `composer cs-check` — all green.

## Verification

```bash
./vendor/bin/phpunit packages/entity/tests/Unit/Attribute/
composer phpstan
composer cs-check
```

## Notes

- Use named-argument-only style in tests (`new Field(stored: FieldStorage::Data)`) to avoid relying on parameter ordering.
- Keep changes minimal — no docblock cleanups outside the touched lines, no reformatting.
