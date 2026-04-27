---
work_package_id: "WP04"
title: "FR-002 + FR-003 cannot-infer cases"
dependencies: ["WP02"]
planning_base_branch: "main"
merge_target_branch: "main"
branch_strategy: "Planning artifacts were generated on main; completed changes must merge back into main."
subtasks:
  - "T011"
  - "T012"
  - "T013"
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
  - "packages/entity/tests/PhpStan/data/cannotInferUntyped.php"
  - "packages/entity/tests/PhpStan/data/cannotInferUnion.php"
tags: []
history:
  - timestamp: "2026-04-27T07:42:00Z"
    agent: "system"
    action: "Prompt generated via /spec-kitty.tasks"
---

# Work Package Prompt: WP04 — FR-002 + FR-003 cannot-infer cases

## Objective

Detect `#[Field]` placed on a property whose PHP type is missing, union, or
intersection — when no explicit `type:` is given. Mirror the runtime's
`FieldTypeInferrer::cannotInferException()` wording exactly (FR-007).

## Subtask Guidance

### T011 — FR-002: untyped

In the rule's `processNode()`, after the FR-001 check returns (or in the same
pass), when:

- a `#[Field]` is present
- AND the attribute's `type:` argument is null/absent
- AND `$node->type === null` (untyped property)

emit:

```text
Cannot infer field type for {class}::${property} (property has no type declaration). Hint: declare a supported property type (string, int, bool, float, array, \DateTimeImmutable, or a backed enum) or pass type: explicitly to #[Field].
```

with identifier `field.cannotInfer`. The leading sentence and hint must
string-match `FieldTypeInferrer::cannotInferException()` output.

### T012 — FR-003: union or intersection

Same gating, but when `$node->type instanceof Node\UnionType` or
`Node\IntersectionType`:

- `Node\UnionType` → reason `union types are not supported`
- `Node\IntersectionType` → reason `intersection types are not supported`

Plug the reason into the same template as FR-002. Identifier remains
`field.cannotInfer`.

### T013 — Fixtures + tests

`tests/PhpStan/data/cannotInferUntyped.php`:

```php
<?php
namespace Waaseyaa\Entity\Tests\PhpStan\Fixtures;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;

final class BadUntyped extends ContentEntityBase
{
    #[Field]
    public $anything;
}
```

`tests/PhpStan/data/cannotInferUnion.php`:

```php
<?php
namespace Waaseyaa\Entity\Tests\PhpStan\Fixtures;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;

final class BadUnion extends ContentEntityBase
{
    #[Field]
    public string|int $either = 'x';
}
```

In `FieldAttributeRuleTest`, add `testCannotInferUntyped()` and
`testCannotInferUnion()` asserting the exact runtime-equivalent message and
correct line number.

## Validation

- [ ] Both error messages string-equal what `FieldTypeInferrer::cannotInferException()` produces for an equivalent reflection property.
- [ ] Identifier `field.cannotInfer` used for both.
- [ ] No false positive when `#[Field(type: 'string')]` is given on the same property shape.
