---
work_package_id: WP03
title: 'F6: FormFieldDescriptor + FormDescriptorBuilder'
dependencies:
- WP01
requirement_refs:
- FR-015
- FR-016
- NFR-005
- NFR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T011
- T012
- T013
- T014
history:
- date: '2026-04-27'
  note: Generated from plan.md + data-model.md + contracts/.
authoritative_surface: packages/field/src/Form/
execution_mode: code_change
mission_id: 01KQ7M1PHWD8QAQPJC91RAVE0T
mission_slug: single-entity-work-surface-01KQ7M1P
owned_files:
- packages/field/src/Form/FormFieldDescriptor.php
- packages/field/src/Form/FormDescriptorBuilder.php
- packages/field/tests/Unit/Form/FormFieldDescriptorTest.php
- packages/field/tests/Unit/Form/FormDescriptorBuilderTest.php
tags: []
---

# WP03 — F6: FormFieldDescriptor + FormDescriptorBuilder

## Objective

Ship the F6 primitive: a server-side helper that, given an entity and a bundle, walks the `FieldDefinitionRegistry` and produces an ordered list of `FormFieldDescriptor` value objects ready for a consumer's template layer to render. **No HTML, no Twig, no Vue output** — the builder emits structured data only (planning Q2: A).

## Context (read first)

- **spec.md** FR-015 — exact behavioral contract.
- **data-model.md § 4** — `FormFieldDescriptor` shape; § 2 — enriched `FieldDefinition` with `getGroup()`, `getPromptAliases()`.
- **contracts/README.md** F6 — acceptance criteria.
- **Q2: A** — no HTML in `waaseyaa/field`. Reject any temptation to add a Twig template, an HTML string concatenation helper, or a Vue/Inertia adapter inside this package.
- **`packages/access/src/FieldAccessPolicyInterface.php`** + `EntityAccessHandler` — the source of read-only signals when an account is provided.

## Branch Strategy

- **Planning base**: `main` (after WP01 lands)
- **Final merge target**: `main`
- Lane via `finalize-tasks`. Use `spec-kitty agent action implement WP03 --agent <name> --mission single-entity-work-surface-01KQ7M1P`.

## Subtasks

### T011 — `FormFieldDescriptor` value object

**File**: `packages/field/src/Form/FormFieldDescriptor.php`

```php
<?php
declare(strict_types=1);

namespace Waaseyaa\Field\Form;

final readonly class FormFieldDescriptor
{
    /** @param list<string> $errors */
    public function __construct(
        public string $name,
        public string $type,
        public string $label,
        public string $group,
        public mixed $value,
        public bool $readOnly,
        public bool $required,
        public array $errors = [],
    ) {}
}
```

**Validation**:
- `final readonly class` PHP 8.4 idiom.
- Public readonly properties accessible directly; no getters needed.
- PHPStan level 5 clean.

### T012 — `FormDescriptorBuilder::build()`

**File**: `packages/field/src/Form/FormDescriptorBuilder.php`

**Constructor**:

```php
public function __construct(
    private readonly FieldDefinitionRegistryInterface $registry,
    private readonly ?\Waaseyaa\Access\EntityAccessHandler $accessHandler = null,
) {}
```

**Method**:

```php
/**
 * @return list<FormFieldDescriptor>
 */
public function build(
    \Waaseyaa\Entity\EntityInterface $entity,
    string $bundle,
    ?\Waaseyaa\Access\AccountInterface $account = null,
): array;
```

**Implementation**:

1. Look up fields: `$fields = $this->registry->bundleFieldsFor($entity->getEntityTypeId(), $bundle);`
2. Iterate in the registry's iteration order (preserves declaration order from compiler).
3. For each `FieldDefinition $field`:
   - `$value = $entity->get($field->getName())`. If the field item list is empty, use `null`.
   - `$readOnly = $field->isReadOnly();`
   - If `$this->accessHandler !== null && $account !== null`:
     - Run `$this->accessHandler->fieldAccess($entity, $field->getName(), 'update', $account)`.
     - If result is `Forbidden`, set `$readOnly = true`.
     - If result is anything else (Allowed, Neutral), do not change `$readOnly`.
   - `$label = $field->getLabel() !== '' ? $field->getLabel() : ucfirst($field->getName());`
   - Build `new FormFieldDescriptor(name: $field->getName(), type: $field->getType(), label: $label, group: $field->getGroup(), value: $value, readOnly: $readOnly, required: $field->isRequired())`.
4. Return the descriptor list.

**Important**:
- Iteration order is the registry's iteration order. Do not sort by group — grouping is the consumer's responsibility (typical: group descriptors at render time).
- Do not throw if the bundle has no registered fields — return `[]`.
- Do not throw if `$entity->get(...)` returns null/empty — set `value = null`.

### T013 — `FormFieldDescriptor` unit test

**File**: `packages/field/tests/Unit/Form/FormFieldDescriptorTest.php`

**Cases**:
- Constructor accepts all fields, exposes them as public readonly.
- Default `errors = []`.
- Cannot mutate properties post-construction (PHPUnit-style: `expectException(\Error::class)` when assigning, or just confirm `final readonly class`).

### T014 — `FormDescriptorBuilder` unit test

**File**: `packages/field/tests/Unit/Form/FormDescriptorBuilderTest.php`

**Cases**:
- Bundle with three fields → `build()` returns three descriptors in registration order.
- Each descriptor's `value` matches `$entity->get($name)`.
- `readOnly` is `false` when `FieldDefinition::isReadOnly()` is `false` and no access handler is provided.
- `readOnly` is `true` when `FieldDefinition::isReadOnly()` is `true`, regardless of access handler.
- `readOnly` becomes `true` when access handler is provided and `fieldAccess` returns `Forbidden` for `update`.
- `readOnly` stays `false` when access handler is provided and `fieldAccess` returns `Allowed`.
- `readOnly` stays as-per-field-definition when access handler is provided and `fieldAccess` returns `Neutral`.
- Empty bundle (registry returns `[]`) → `build()` returns `[]`, no exception.
- Field with empty `getLabel()` → descriptor's `label` is `ucfirst($name)`.
- Field with non-empty `getLabel()` → descriptor's `label` matches definition's label.
- Group preserved through the descriptor.
- `required` preserved through the descriptor.

Use stubs/fakes for `FieldDefinitionRegistryInterface`, `EntityInterface`, `EntityAccessHandler`, `AccountInterface`. PHPUnit can mock interfaces directly; for `final` classes, build small anonymous classes.

## Definition of Done

- [ ] `FormFieldDescriptor` value object with all 8 properties as public readonly.
- [ ] `FormDescriptorBuilder::build()` returns ordered descriptors per spec FR-015.
- [ ] `readOnly` resolution: union of `FieldDefinition::isReadOnly()` OR (`accessHandler` + `account` non-null AND `fieldAccess('update')` Forbidden).
- [ ] Label fallback to `ucfirst($name)` when `FieldDefinition::getLabel()` is empty.
- [ ] Empty bundle returns `[]` without exception.
- [ ] Builder unit test covers all cases above (10+ assertions).
- [ ] Descriptor unit test covers immutability and defaults.
- [ ] No HTML, no Twig, no markup output anywhere in `packages/field/src/Form/`.
- [ ] `composer phpstan`, `composer cs-check`, PHPUnit pass.
- [ ] No code changes outside `owned_files`.

## Risks

| Risk | Mitigation |
|---|---|
| `FieldAccessPolicyInterface` resolution requires the entity's policy be discoverable; tests need real access machinery | Use a fake `EntityAccessHandler` whose `fieldAccess` returns a controllable `AccessResult`. Real access policy discovery is exercised in WP10's end-to-end test, not here. |
| `EntityInterface::get()` returns a `FieldItemListInterface`, not a scalar — descriptor's `mixed $value` may be a list | This is correct. The `value` field intentionally accepts whatever the field item list returns; the consumer's template layer handles single-value vs multi-value rendering. Document this in the descriptor PHPDoc. |
| Forgetting that registration order = iteration order | Test explicitly: register fields A, B, C in that order; assert `build()` returns descriptors in `[A, B, C]` order. |

## Reviewer guidance

- Reject any HTML/Twig/Vue rendering inside `packages/field/src/Form/`. Q2 lock is firm.
- Verify `FormFieldDescriptor` is `final readonly class` and uses public readonly properties (PHP 8.4 idiom).
- Verify the access-handler branch is conditional on `accessHandler !== null && account !== null` — both must be present, mirroring the paired-nullable pattern in CLAUDE.md gotchas.
- Verify the test for `Neutral` access result — the field should retain its definition's `isReadOnly()` setting (Neutral does not flip readOnly).
- Confirm no edits to CHANGELOG.md (deferred to WP10).

## Implementation command

```bash
spec-kitty agent action implement WP03 --agent <agent-name> --mission single-entity-work-surface-01KQ7M1P
```

Depends on WP01.
