---
work_package_id: "WP03"
title: "FR-001 detect non-public property"
dependencies: ["WP02"]
planning_base_branch: "main"
merge_target_branch: "main"
branch_strategy: "Planning artifacts were generated on main; completed changes must merge back into main."
subtasks:
  - "T009"
  - "T010"
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
  - "packages/entity/tests/PhpStan/data/nonPublicProperty.php"
tags: []
history:
  - timestamp: "2026-04-27T07:42:00Z"
    agent: "system"
    action: "Prompt generated via /spec-kitty.tasks"
---

# Work Package Prompt: WP03 — FR-001 detect non-public property

## Objective

Detect `#[Field]` placed on a non-public property and emit a PHPStan error
with stable identifier `field.nonPublic` whose message matches the runtime's
contract (currently the runtime relies on reflection succeeding via
`->isPublic()` checks elsewhere; the wording below is mission-defined).

## Subtask Guidance

### T009 — Detection logic

In `processNode()`:

1. Read attribute groups from `$node->attrGroups`. Skip property if no `#[Field]` present (match by FQCN `Waaseyaa\Entity\Attribute\Field`).
2. Determine visibility:
   - `$node->flags & Node\Stmt\Class_::MODIFIER_PUBLIC` → public, return.
   - else → not public.
3. Determine property name from `$node->props[0]->name->name` (PHPStan splits multi-property statements; iterate `$node->props` for safety).
4. Determine class FQCN via `$scope->getClassReflection()?->getName()`.
5. Build error:
   ```php
   RuleErrorBuilder::message(sprintf(
       'Field attribute requires public property; got %s on %s::$%s',
       $visibilityWord,    // 'private' | 'protected'
       $className,
       $propertyName,
   ))
       ->identifier('field.nonPublic')
       ->build();
   ```

Helper `Waaseyaa\Entity\PhpStan\Internal\AttributeFinder` (private to the namespace) MAY be extracted to keep `processNode()` short — that's at the implementer's discretion.

### T010 — Fixture + test

`packages/entity/tests/PhpStan/data/nonPublicProperty.php`:

```php
<?php
namespace Waaseyaa\Entity\Tests\PhpStan\Fixtures;

use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;

final class BadNonPublic extends ContentEntityBase
{
    #[Field]
    protected string $secret = '';
}
```

`packages/entity/tests/PhpStan/FieldAttributeRuleTest.php`:

```php
<?php
namespace Waaseyaa\Entity\Tests\PhpStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Waaseyaa\Entity\PhpStan\FieldAttributeRule;

/** @extends RuleTestCase<FieldAttributeRule> */
final class FieldAttributeRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new FieldAttributeRule();
    }

    public function testNonPublicProperty(): void
    {
        $this->analyse([__DIR__ . '/data/nonPublicProperty.php'], [
            [
                'Field attribute requires public property; got protected on Waaseyaa\\Entity\\Tests\\PhpStan\\Fixtures\\BadNonPublic::$secret',
                10, // line of the property declaration; adjust if fixture differs
            ],
        ]);
    }
}
```

## Validation

- [ ] Rule emits exactly one error for the fixture; identifier `field.nonPublic`.
- [ ] No errors emitted on a fixture with `public` visibility (add a sibling `goodPublic.php` if helpful, or assert in a `testPublicPropertyHasNoError` method).
- [ ] `vendor/bin/phpunit packages/entity/tests/PhpStan` green.
- [ ] `vendor/bin/phpstan analyse` against the entity package's own `src/` still green.
