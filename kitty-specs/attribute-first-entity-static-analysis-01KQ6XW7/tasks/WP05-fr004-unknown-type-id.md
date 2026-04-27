---
work_package_id: "WP05"
title: "FR-004 unknown type id"
dependencies: ["WP02"]
planning_base_branch: "main"
merge_target_branch: "main"
branch_strategy: "Planning artifacts were generated on main; completed changes must merge back into main."
subtasks:
  - "T014"
  - "T015"
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
  - "packages/entity/tests/PhpStan/data/unknownTypeId.php"
tags: []
history:
  - timestamp: "2026-04-27T07:42:00Z"
    agent: "system"
    action: "Prompt generated via /spec-kitty.tasks"
---

# Work Package Prompt: WP05 — FR-004 unknown type id

## Objective

Detect `#[Field(type: '<unknown>')]` whose value is not in
`FieldTypeInferrer::VALID_TYPE_IDS`. Mirror the runtime's
`FieldTypeInferrer::assertValidTypeId()` exception message.

## Subtask Guidance

### T014 — Detection

When `#[Field(type: 'X')]` is present, extract `'X'` from the AST attribute
arg. The `type:` arg is named (PHP 8 named args) — find it by parameter name
in `$attribute->args`, falling back to positional arg index 0 (`type` is the
first ctor parameter on `Field`). For non-string-literal expressions (e.g.
constant references), skip with no error — PHPStan's expression analysis is
out of scope; only literal strings are checked.

If `'X' !\in FieldTypeInferrer::VALID_TYPE_IDS`, emit:

```text
Unknown field type id "X" on {class}::${property}. Valid ids: boolean, computed, date, datetime, decimal, email, entity_reference, enum, file, float, image, integer, json, link, list, string, text. Hint: pass one of the registered field-type ids to #[Field(type: ...)] or omit it to use inference.
```

(The valid id list is read from `FieldTypeInferrer::VALID_TYPE_IDS` at rule
runtime — do **not** hard-code the comma-joined string.)

Identifier: `field.unknownType`.

### T015 — Fixture + test

`tests/PhpStan/data/unknownTypeId.php`:

```php
<?php
namespace Waaseyaa\Entity\Tests\PhpStan\Fixtures;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;

final class BadUnknownType extends ContentEntityBase
{
    #[Field(type: 'integerr')]
    public string $count = '';
}
```

`testUnknownTypeId()` in `FieldAttributeRuleTest` asserts the exact message
and line. Construct the expected message at test-time by calling the runtime
helper (or invoking `FieldTypeInferrer::infer()` and capturing the
`EntityMetadataException` message) so the test breaks if the runtime
wording drifts.

## Validation

- [ ] Error includes the offending id verbatim and the joined valid-id list.
- [ ] Valid ids list comes from `FieldTypeInferrer::VALID_TYPE_IDS` (no duplicate).
- [ ] No false positive when `type:` is one of the 16 valid ids.
- [ ] No spurious error when `type:` is a `Field::TYPE_*`-style class constant (skipped because not a literal).
