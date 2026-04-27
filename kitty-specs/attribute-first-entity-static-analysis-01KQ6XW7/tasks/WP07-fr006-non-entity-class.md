---
work_package_id: "WP07"
title: "FR-006 class does not extend ContentEntityBase"
dependencies: ["WP02"]
planning_base_branch: "main"
merge_target_branch: "main"
branch_strategy: "Planning artifacts were generated on main; completed changes must merge back into main."
subtasks:
  - "T018"
  - "T019"
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
  - "packages/entity/tests/PhpStan/data/notEntityClass.php"
tags: []
history:
  - timestamp: "2026-04-27T07:42:00Z"
    agent: "system"
    action: "Prompt generated via /spec-kitty.tasks"
---

# Work Package Prompt: WP07 — FR-006 class does not extend ContentEntityBase

## Objective

Detect `#[Field]` on a property of a class that is not a transitive subclass
of `Waaseyaa\Entity\ContentEntityBase`. The runtime never raises this error
explicitly (it surfaces as a metadata read failure later); the rule
formalizes it at static-analysis time.

## Subtask Guidance

### T018 — Detection

In `processNode()`:

1. Get class reflection: `$classRef = $scope->getClassReflection()` (skip if null — anonymous class etc.).
2. Walk parents via PHPStan's reflection: `$classRef->isSubclassOf(ContentEntityBase::class)`. The framework already enforces this elsewhere via direct `instanceof`; PHPStan's `ClassReflection::isSubclassOf()` walks the chain.
3. If false, emit:

   ```text
   #[Field] used on {class}::${property} but {class} does not extend Waaseyaa\Entity\ContentEntityBase
   ```

   Identifier: `field.notEntity`.

Place this check first in the per-attribute loop — if the class isn't an
entity, the other checks add noise; once `field.notEntity` is reported, skip
the rest for that property to avoid cascading errors.

### T019 — Fixture + test

`tests/PhpStan/data/notEntityClass.php`:

```php
<?php
namespace Waaseyaa\Entity\Tests\PhpStan\Fixtures;
use Waaseyaa\Entity\Attribute\Field;

final class JustADto
{
    #[Field]
    public string $name = '';
}
```

(Note: no `extends ContentEntityBase`.)

`testNotEntityClass()` asserts the exact message and line, and verifies that
no other rule errors are reported for this fixture (cascade suppression).

## Validation

- [ ] Detected for direct non-extension and for classes extending an unrelated parent.
- [ ] Not reported when `extends ContentEntityBase` is present (covered by all other fixtures, but add a positive `goodEntity.php` if absent).
- [ ] No double-reporting: when `field.notEntity` fires, FR-001..FR-005 don't.
