---
work_package_id: WP01
title: FieldTypeInferrer public helpers
dependencies: []
requirement_refs:
- FR-005
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-attribute-first-entity-static-analysis-01KQ6XW7
base_commit: 38018c4e9dd18f4df6f21a742997417fd937c471
created_at: '2026-04-27T07:57:51.205399+00:00'
subtasks:
- T001
- T002
- T003
phase: Phase 1 - Foundations
assignee: ''
agent: ''
shell_pid: '36176'
history:
- timestamp: '2026-04-27T07:42:00Z'
  agent: system
  action: Prompt generated via /spec-kitty.tasks
authoritative_surface: packages/entity/src/Attribute/FieldTypeInferrer
execution_mode: code_change
mission_id: 01KQ6XW7Y3QD0JJ7JTP9JCSDPM
mission_slug: attribute-first-entity-static-analysis-01KQ6XW7
owned_files:
- packages/entity/src/Attribute/FieldTypeInferrer.php
- packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php
tags: []
---

# Work Package Prompt: WP01 — FieldTypeInferrer public helpers

## Branch Strategy

- **Planning base**: `main`.
- **Merge target**: `main`.
- **Execution worktree**: allocated per lane by `lanes.json` after `finalize-tasks`. Run `spec-kitty agent action implement WP01 --agent <name>` to enter the lane.

## Objective

Expose two pure, side-effect-free static helpers on `FieldTypeInferrer` so the
forthcoming PHPStan rule can mirror the runtime's compatibility-group and
type-inference logic without re-encoding the table (C-002). No change to
`infer()` semantics (C-004).

## Context

- `FieldTypeInferrer::COMPATIBILITY_GROUPS` and `FieldTypeInferrer::SCALAR_MAP` are currently `private const`.
- `FieldTypeInferrer::VALID_TYPE_IDS` is already `public const` — reused as-is by the rule.
- The rule needs to: (a) ask "is field-type X compatible with field-type Y?" and (b) ask "what field-type does PHP type name `int` infer to?". Helpers answer both.

## Subtask Guidance

### T001 — `compatibilityGroups()`

Add public static method:

```php
/** @return list<list<string>> */
public static function compatibilityGroups(): array
{
    return self::COMPATIBILITY_GROUPS;
}
```

Place it after the existing private `isCompatible()` method. Keep
`COMPATIBILITY_GROUPS` private; this helper is the public seam.

### T002 — `inferFromPhpTypeName()`

Add public static method that mirrors the inference branch of `infer()`
without requiring a `\ReflectionProperty`:

```php
/**
 * @param array<string,mixed> $settings  Out-parameter for backed-enum metadata.
 */
public static function inferFromPhpTypeName(?string $phpTypeName, array &$settings = []): ?string
{
    if ($phpTypeName === null) {
        return null;
    }
    return self::mapPhpTypeToFieldType($phpTypeName, $settings);
}
```

This is a thin public wrapper over the existing private
`mapPhpTypeToFieldType()`. Do not duplicate logic.

### T003 — Tests

Extend `packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php`:

- One test method `testCompatibilityGroupsExposesPrivateConstantVerbatim()` that asserts the helper's return equals the expected nested list (hard-code the four groups; if the constant changes the test breaks loud, which is the point).
- One test method `testInferFromPhpTypeName()` with a data provider:
  - `'string'` → `'string'`
  - `'int'` → `'integer'`
  - `'bool'` → `'boolean'`
  - `'float'` → `'float'`
  - `'array'` → `'json'`
  - `\DateTimeImmutable::class` → `'datetime'`
  - A backed-enum class (use existing test fixture if any; else inline) → `'enum'` and asserts `$settings['enum_class']` is populated.
  - `null` → `null`
  - `'\StdClass'` (unsupported) → `null`

## Files

- `packages/entity/src/Attribute/FieldTypeInferrer.php` — additive only; no modification to existing methods.
- `packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php` — additions.

## Validation

- [ ] No diff to `FieldTypeInferrer::infer()` body.
- [ ] `compatibilityGroups()` returns identical structure to private constant (asserted).
- [ ] `inferFromPhpTypeName()` matches every row of the existing inference table (asserted).
- [ ] Existing `FieldTypeInferrerTest` still passes.
- [ ] `vendor/bin/phpunit packages/entity` green.
- [ ] `vendor/bin/phpstan analyse packages/entity/src` green.
