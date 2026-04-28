---
work_package_id: WP01
title: Inferrer compatibility rule + tests
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- NFR-004
- C-001
- C-004
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-inferrer-entity-reference-compat-01KQ6SC0
base_commit: d99dc776715cadfb675439d3b75985e840a5c37c
created_at: '2026-04-28T13:01:38.876403+00:00'
subtasks: []
assignee: claude
agent: claude
shell_pid: '38540'
history: []
authoritative_surface: packages/entity/
execution_mode: code_change
owned_files:
- packages/entity/src/Attribute/FieldTypeInferrer.php
- packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php
- packages/entity/tests/Fixtures/AttributeFirstEntities/InferrerTestFixtures.php
tags: []
---

# WP01 — Inferrer compatibility rule + tests

## Goal

Extend `FieldTypeInferrer::isCompatible()` to accept `int`/`?int`/`string`/`?string` PHP types when explicitly overridden to `#[Field(type: 'entity_reference', ...)]`. Use an asymmetric one-way allowlist; do **not** mutate the symmetric `COMPATIBILITY_GROUPS` table or the `compatibilityGroups()` public seam.

## Context

- `packages/entity/src/Attribute/FieldTypeInferrer.php:175-205` — current `COMPATIBILITY_GROUPS`, `isCompatible()`, `compatibilityGroups()`.
- `packages/entity/src/Attribute/FieldTypeInferrer.php:52-59` — `SCALAR_MAP` shows `int → 'integer'` and `string → 'string'` (these are the inferred ids the new rule whitelists).
- `packages/entity/tests/Fixtures/AttributeFirstEntities/InferrerTestFixtures.php` — existing fixture pattern: one public typed property per inference rule (line 39+).
- `packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php` — existing data-provider `inferenceCases()` (around line 100); follow the existing `[propertyName, explicitType, expected]` shape.

`packages/entity` is Layer 1 (Core Data); same-layer changes only — no cross-layer impact.

## Acceptance Criteria (from spec)

- **FR-001**: `int` PHP type + `type: 'entity_reference'` accepted; returns `{type: 'entity_reference', required: true, ...}`.
- **FR-002**: `?int` PHP type + `type: 'entity_reference'` accepted; returns `{type: 'entity_reference', required: false, ...}`.
- **FR-003**: `string` and `?string` PHP types + `type: 'entity_reference'` accepted (symmetric to FR-001/002).
- **FR-004**: `bool`, `float`, `array`, `\DateTimeImmutable`, backed enum + `type: 'entity_reference'` continues to raise `EntityMetadataException` with the existing conflict-message shape.
- **NFR-004**: at least 4 happy-path test cases + 1 rejection case in `FieldTypeInferrerTest`.
- **C-001**: `compatibilityGroups()` public seam returns the same `list<list<string>>` as before this mission. Add a regression test.
- **C-004**: `FieldTypeInferrer::infer()` on a bare `int`/`string`/`?int`/`?string` (no explicit `type:` override) MUST still infer `'integer'` / `'string'`, not `'entity_reference'`. Add a regression test.

## Subtasks

- [ ] T001 — Add four reflection fixtures to `InferrerTestFixtures.php`: `?int $aNullableIntForRef = null;`, `int $anIntForRef = 0;`, `?string $aNullableStringForRef = null;`, `string $aStringForRef = '';`.
- [ ] T002 — Add four happy-path entries to `inferenceCases()` data provider in `FieldTypeInferrerTest.php` covering the four fixtures × `entity_reference` override; expect `{type: 'entity_reference', required: bool, settings: []}`.
- [ ] T003 — Add a dedicated test method `it_rejects_incompatible_scalars_for_entity_reference()` that asserts `bool $aBool` and `float $aFloat` properties + `type: 'entity_reference'` raise `EntityMetadataException` with conflict-message keywords (`'bool'`, `'float'`, `'entity_reference'`).
- [ ] T004 — Add a regression test `it_does_not_infer_entity_reference_from_scalars()` asserting `infer()` on a bare `?int`/`?string` (no explicit type) returns `{type: 'integer'/'string', ...}`, not `'entity_reference'`. Asserts the new rule is asymmetric (C-004).
- [ ] T005 — Implement the rule: add `private const ENTITY_REFERENCE_COMPATIBLE_INFERRED = ['integer', 'string'];` to `FieldTypeInferrer`, then in `isCompatible()` after the symmetric-group check, return `true` when `$explicit === 'entity_reference'` and `\in_array($inferred, self::ENTITY_REFERENCE_COMPATIBLE_INFERRED, true)`. Do not modify `COMPATIBILITY_GROUPS` or `compatibilityGroups()`.

TDD order: T001 → T002 → T003 → T004 (red) → T005 (green).

## Verification

- `./vendor/bin/phpunit packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php` is green and includes ≥4 new happy cases + ≥1 rejection case.
- `./vendor/bin/phpunit packages/entity/tests/Unit/Attribute/` overall is green.
- `composer cs-check` clean.
- `composer phpstan` reports no new findings.
