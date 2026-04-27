---
work_package_id: "WP06"
title: "FR-005 incompatible explicit type"
dependencies: ["WP01", "WP02"]
planning_base_branch: "main"
merge_target_branch: "main"
branch_strategy: "Planning artifacts were generated on main; completed changes must merge back into main."
subtasks:
  - "T016"
  - "T017"
phase: "Phase 2 - Rule logic"
assignee: ""
agent: ""
shell_pid: ""
authoritative_surface: "packages/entity/src/PhpStan/FieldAttributeRule"
execution_mode: "code_change"
mission_id: "01KQ6XW7Y3QD0JJ7JTP9JCSDPM"
mission_slug: "attribute-first-entity-static-analysis-01KQ6XW7"
owned_files:
  - "packages/entity/src/PhpStan/FieldAttributeRule.php"
  - "packages/entity/tests/PhpStan/FieldAttributeRuleTest.php"
  - "packages/entity/tests/PhpStan/data/incompatibleType.php"
tags: []
history:
  - timestamp: "2026-04-27T07:42:00Z"
    agent: "system"
    action: "Prompt generated via /spec-kitty.tasks"
---

# Work Package Prompt: WP06 — FR-005 incompatible explicit type

## Objective

Detect `#[Field(type: 'X')]` where `'X'` is incompatible with the property's
declared PHP type per `FieldTypeInferrer::compatibilityGroups()`. Mirror the
runtime's `FieldTypeInferrer::conflictException()` wording.

## Subtask Guidance

### T016 — Detection

For each `#[Field]` with a literal `type: 'X'` and a single-named PHP type
declaration:

1. Extract PHP type name from `$node->type` (skip if union/intersection — that's FR-003 territory).
2. `$inferred = FieldTypeInferrer::inferFromPhpTypeName($phpTypeName)` (introduced in WP01).
3. If `$inferred === null` → no compatibility check possible; skip.
4. If `$inferred === 'X'` → match; skip.
5. Else, check `FieldTypeInferrer::compatibilityGroups()`: if both `$inferred` and `'X'` appear in the same group → compatible; skip.
6. Else, emit:

   ```text
   Conflicting field type for {class}::${property}: PHP type "{phpTypeName}" infers field type "{inferred}" but #[Field(type: "{X}")] was given. Hint: remove the explicit type:, change the property type, or pick a compatible field-type id.
   ```

   Identifier: `field.incompatibleType`.

The wording must string-equal the runtime's `conflictException()`. Build the
expected message in tests by invoking the runtime helper rather than
hard-coding it.

### T017 — Fixture + test

`tests/PhpStan/data/incompatibleType.php`:

```php
<?php
namespace Waaseyaa\Entity\Tests\PhpStan\Fixtures;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;

final class BadIncompatible extends ContentEntityBase
{
    #[Field(type: 'integer')]
    public string $count = '';
}
```

`testIncompatibleType()` asserts exact wording and line.

Add a no-op fixture `tests/PhpStan/data/compatibleOverride.php` with
`#[Field(type: 'text')] public string $body = '';` and a `testCompatibleOverrideHasNoError()` to guard against over-flagging within the same compatibility group.

## Validation

- [ ] Error wording matches `FieldTypeInferrer::conflictException()` byte-for-byte for the same input.
- [ ] No false positive within compat groups: `string ↔ text|email|link`, `int ↔ list`, `float ↔ decimal`, `datetime ↔ date`.
- [ ] Backed-enum + `type: 'string'` case (`enum` vs `string`, not in any group) is reported — verify with an extra fixture if reasonable; otherwise document deferral and file follow-up.
