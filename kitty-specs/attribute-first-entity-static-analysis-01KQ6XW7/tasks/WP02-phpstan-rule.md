---
work_package_id: WP02
title: FieldAttributeRule (all 6 detection branches + wiring)
dependencies:
- WP01
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-005
- FR-006
- FR-007
- FR-008
- FR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T004
- T005
- T006
- T007
- T008
- T009
- T010
- T011
- T012
- T013
- T014
- T015
- T016
- T017
- T018
- T019
phase: Phase 2 - Rule
assignee: ''
agent: ''
history:
- timestamp: '2026-04-27T07:42:00Z'
  agent: system
  action: Prompt generated via /spec-kitty.tasks
authoritative_surface: packages/entity/src/PhpStan/FieldAttributeRule
execution_mode: code_change
mission_id: 01KQ6XW7Y3QD0JJ7JTP9JCSDPM
mission_slug: attribute-first-entity-static-analysis-01KQ6XW7
owned_files:
- packages/entity/composer.json
- packages/entity/src/PhpStan/FieldAttributeRule.php
- packages/entity/phpstan-rules.neon
- phpstan.neon
- packages/entity/tests/PhpStan/FieldAttributeRuleTest.php
- packages/entity/tests/PhpStan/data/nonPublicProperty.php
- packages/entity/tests/PhpStan/data/cannotInferUntyped.php
- packages/entity/tests/PhpStan/data/cannotInferUnion.php
- packages/entity/tests/PhpStan/data/unknownTypeId.php
- packages/entity/tests/PhpStan/data/incompatibleType.php
- packages/entity/tests/PhpStan/data/compatibleOverride.php
- packages/entity/tests/PhpStan/data/notEntityClass.php
tags: []
---

# Work Package Prompt: WP02 — FieldAttributeRule (all 6 detection branches + wiring)

## Branch Strategy

- **Planning base**: `main`.
- **Merge target**: `main`.
- **Execution worktree**: lane-allocated post `finalize-tasks`.

## Objective

Land the complete `FieldAttributeRule` PHPStan rule with all six detection
branches (FR-001..FR-006), its registration in the framework's PHPStan
config, and the full `RuleTestCase`-based test suite with one fixture per
detection branch. After this WP merges, every misuse listed in the spec is
caught at static-analysis time.

This is one large WP because all six detection branches share
`FieldAttributeRule.php` and `FieldAttributeRuleTest.php`; the lane allocator
requires disjoint file ownership across WPs.

## Subtasks (16)

### Wiring (T004..T008)

**T004** — Add `phpstan/phpstan` to `packages/entity/composer.json` `require-dev` (pin to whatever version is already in repo-root `composer.lock`; run `composer update --lock packages/entity` if needed).

**T005** — Create `packages/entity/src/PhpStan/FieldAttributeRule.php` skeleton: `final class FieldAttributeRule implements PHPStan\Rules\Rule` with `getNodeType(): string => Node\Stmt\Property::class`. `processNode()` will be filled by the detection-branch tasks below.

**T006** — Create `packages/entity/phpstan-rules.neon`:

```neon
services:
    -
        class: Waaseyaa\Entity\PhpStan\FieldAttributeRule
        tags:
            - phpstan.rules.rule
```

**T007** — Append `- packages/entity/phpstan-rules.neon` to repo-root `phpstan.neon` `includes:` block.

**T008** — Verify `vendor/bin/phpstan analyse --no-progress` exits 0 and `phpstan-baseline.neon` is unchanged. Confirm rule class is reachable (temporarily emit a fake error from `processNode()` and confirm PHPStan reports it; revert before commit).

### Detection branches (T009..T018) — implemented inside `processNode()`

For every `#[Field]` attribute on each property in the AST node:

**T009 — FR-001 non-public.** If the property's flags lack `MODIFIER_PUBLIC`, emit error with identifier `field.nonPublic`:
```
Field attribute requires public property; got {visibility} on {class}::${property}
```

**T010 — FR-006 non-entity-class (cascade gate).** Resolve declaring class via `$scope->getClassReflection()`. If it does not extend `Waaseyaa\Entity\ContentEntityBase` (use `ClassReflection::isSubclassOf()`), emit error with identifier `field.notEntity`:
```
#[Field] used on {class}::${property} but {class} does not extend Waaseyaa\Entity\ContentEntityBase
```
**Skip the remaining checks for this property when this fires** to avoid cascading noise.

**T011 — FR-002 cannot-infer (untyped).** When attribute's `type:` arg is null/absent AND `$node->type === null`, emit error with identifier `field.cannotInfer` whose message string-equals what `FieldTypeInferrer::cannotInferException()` produces for the same property — the "property has no type declaration" branch.

**T012 — FR-003 cannot-infer (union/intersection).** Same gating, but when `$node->type instanceof Node\UnionType` or `Node\IntersectionType`. Plug `union types are not supported` / `intersection types are not supported` reason into the same template. Same identifier.

**T013 — FR-004 unknown type id.** When attribute has a literal `type: 'X'` (named or first positional arg) and `'X' !\in FieldTypeInferrer::VALID_TYPE_IDS`, emit error with identifier `field.unknownType` whose message string-equals `FieldTypeInferrer::assertValidTypeId()`'s wording. Read the valid id list from `FieldTypeInferrer::VALID_TYPE_IDS` at rule runtime — do not hard-code the joined string.

**T014 — FR-005 incompatible explicit type.** When attribute has a literal `type: 'X'` and the property has a single-named type `T`:
1. `$inferred = FieldTypeInferrer::inferFromPhpTypeName($T)` (the public helper added in WP01).
2. If `$inferred === null` or `$inferred === 'X'` → skip.
3. Else, `$groups = FieldTypeInferrer::compatibilityGroups()`. If both `$inferred` and `'X'` appear in the same group → skip.
4. Else, emit error with identifier `field.incompatibleType` whose message string-equals `FieldTypeInferrer::conflictException()` for the same input.

Detection-branch order in `processNode()`: FR-001, FR-006 (cascade gate), then FR-002 / FR-003 / FR-004 / FR-005 in any order.

### Tests + fixtures (T015..T019)

**T015** — Create `packages/entity/tests/PhpStan/FieldAttributeRuleTest.php` extending `PHPStan\Testing\RuleTestCase<FieldAttributeRule>`. One `test*` method per FR plus a `testCompatibleOverrideHasNoError` for the same-group no-false-positive guard.

**T016..T019** — Fixtures under `packages/entity/tests/PhpStan/data/`:

- `nonPublicProperty.php` — `#[Field] protected string $secret = '';` on a `ContentEntityBase` subclass.
- `cannotInferUntyped.php` — `#[Field] public $anything;`.
- `cannotInferUnion.php` — `#[Field] public string|int $either;`.
- `unknownTypeId.php` — `#[Field(type: 'integerr')] public string $count;`.
- `incompatibleType.php` — `#[Field(type: 'integer')] public string $count;`.
- `compatibleOverride.php` — `#[Field(type: 'text')] public string $body;` (must produce zero errors).
- `notEntityClass.php` — `#[Field] public string $name;` on a class **not** extending `ContentEntityBase`.

All fixtures use namespace `Waaseyaa\Entity\Tests\PhpStan\Fixtures` and import `Waaseyaa\Entity\Attribute\Field` and (where relevant) `Waaseyaa\Entity\ContentEntityBase`.

### FR-007 (string-equality with runtime)

In each `test*` method, build the expected error message by **invoking the runtime helper** rather than hard-coding the string. Pattern:

```php
$prop = (new \ReflectionClass(\Waaseyaa\Entity\Tests\PhpStan\Fixtures\BadUntyped::class))
    ->getProperty('anything');
try {
    FieldTypeInferrer::infer($prop, new Field());
    $this->fail();
} catch (EntityMetadataException $e) {
    $expected = $e->getMessage();
}

$this->analyse([__DIR__ . '/data/cannotInferUntyped.php'], [[$expected, 9]]);
```

This mechanically enforces FR-007 for FR-002..FR-005. For FR-001 and FR-006 (no runtime equivalent), assert against literal mission-defined wording and document origin in the test docblock.

## Validation

- [ ] All 7 fixtures produce the expected number of errors and zero unexpected errors.
- [ ] Error messages for FR-002..FR-005 string-equal `FieldTypeInferrer` exception messages for equivalent inputs.
- [ ] All identifiers (`field.nonPublic`, `field.cannotInfer`, `field.unknownType`, `field.incompatibleType`, `field.notEntity`) used.
- [ ] Cascade suppression works: `notEntityClass.php` reports exactly one error.
- [ ] `vendor/bin/phpstan analyse --no-progress` against the entire monorepo exits 0 with no new errors on existing entity-using packages.
- [ ] `vendor/bin/phpunit packages/entity/tests/PhpStan` green.
