---
work_package_id: WP03
title: TranslatableInterface contract
dependencies: []
requirement_refs:
- C-002
- FR-008
- FR-009
- FR-012
- FR-015
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
<<<<<<< HEAD
base_branch: kitty/mission-m006-translation-hardening-01KS3RY9
base_commit: 090fed7ef7bd015bd8aa6026b5f6257c6ad79707
created_at: '2026-05-21T00:25:19.635151+00:00'
subtasks:
- T001
- T002
shell_pid: "722296"
agent: "claude:opus-4-7:reviewer:reviewer"
=======
subtasks:
- T001
- T002
>>>>>>> kitty/mission-m006-translation-hardening-01KS3RY9-lane-a
history:
- date: '2026-05-20T23:57:09Z'
  author: tasks-materializer
  note: Initial WP file generated
authoritative_surface: packages/entity/src/
execution_mode: code_change
mission_slug: m006-translation-hardening-01KS3RY9
owned_files:
- packages/entity/src/TranslatableInterface.php
- packages/entity/tests/Unit/TranslatableInterfaceContractTest.php
tags: []
---

# WP03 — TranslatableInterface contract

**Mission**: m006-translation-hardening-01KS3RY9
**Closes**: #1447 (MEDIUM)
**Priority**: implement first — no dependencies, compile-time safety gap

## Objective

Declare `fieldLangcode(string $fieldName): ?string` on `TranslatableInterface` so that
consumers writing a non-trait implementation get a PHP compile-time error if they omit
the method, rather than a runtime fatal when `fieldLangcode()` is eventually called.
Confirm via a contract test that the interface now enforces the method and that
`TranslatableEntityTrait` already satisfies it without any source changes.

## Context

- **File**: `packages/entity/src/TranslatableInterface.php` (98 lines, PHP 8.5+)
- **Trait**: `packages/entity/src/TranslatableEntityTrait.php` — `fieldLangcode(string $fieldName): ?string` exists at line 264.
- **Spec FR-008**: "TranslatableInterface declares `public function fieldLangcode(string $fieldName): ?string;`"
- **Spec FR-009**: "TranslatableEntityTrait::fieldLangcode() continues to satisfy the interface declaration without source changes."
- **Spec FR-012**: Contract test asserts (via reflection) that the method is declared on the interface with the expected signature and is satisfied by the trait.
- **Memory `feedback_modern_php_rules.md`**: "contract tests for every extension point" — use `#[CoversNothing]` for contract tests.
- **CLAUDE.md gotcha on PHPUnit**: `createMock()` fails on intersection types and `final class`. Use real anonymous classes here.

No dependency on any other WP. This is the safest WP to implement first.

## Branch Strategy

- **Planning/base branch**: `main`
- **Merge target**: `main`
- Execution worktree is allocated by `finalize-tasks` → `lanes.json`. Implement from
  whichever workspace `spec-kitty agent action implement WP03 --agent <name>` places you in.

## Implementation Command

```bash
spec-kitty agent action implement WP03 --agent claude:sonnet
```

---

## Subtask T001 — Add `fieldLangcode` declaration to `TranslatableInterface`

**Purpose**: Make the PHP type system enforce `fieldLangcode()` on every
`TranslatableInterface` implementor, not just on `TranslatableEntityTrait` users.

**File**: `packages/entity/src/TranslatableInterface.php`

**Steps**:

1. Open the file. The last method declared is `getTranslationLanguages(): array` (lines 91–98).

2. After `getTranslationLanguages()`, add:

```php
/**
 * Returns the stored langcode for a specific field on this translation.
 *
 * Returns `null` when the field has no per-language override and falls back
 * to the entity's default langcode, or when the field name is not recognized.
 *
 * @param string $fieldName The field machine name.
 */
public function fieldLangcode(string $fieldName): ?string;
```

3. Keep the method alphabetically near the other langcode-related methods if desired,
   but end-of-interface placement is also acceptable — consistency with existing order
   matters more than perfect alphabetisation.

4. Do **not** change anything else in the file. No imports, no class-level changes,
   no docblock changes above the interface declaration.

**Validation**:
- [ ] `./vendor/bin/phpunit packages/entity/tests/` passes after the edit (no regressions).
- [ ] `composer cs-check` passes (Pint is happy with the added method).
- [ ] `composer phpstan` passes (level 5; no new errors).
- [ ] `composer dump-autoload --optimize` emits no interface-contract warnings.

---

## Subtask T002 — Contract test: reflection + trait satisfaction

**Purpose**: Prove via automated test (a) that the method is declared on the interface with
the exact expected signature, and (b) that `TranslatableEntityTrait` satisfies the
declaration without source changes.

**File**: `packages/entity/tests/Unit/TranslatableInterfaceContractTest.php` *(new file)*

**Namespace**: `Waaseyaa\Entity\Tests\Unit`

**Pattern**:
- Use `#[CoversNothing]` — this is a contract/reflection test, not a unit coverage test.
- Use `#[Test]` attribute on each test method (PHPUnit 10.5 style; no `@test` annotations).
- Do **not** use `createMock()` here — use a real anonymous class with `use TranslatableEntityTrait`.

**Steps**:

1. Create the file with this skeleton:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\TranslatableInterface;
use Waaseyaa\Entity\TranslatableEntityTrait;

#[CoversNothing]
final class TranslatableInterfaceContractTest extends TestCase
{
    // tests go here
}
```

2. Add test `interfaceDeclaresFieldLangcodeMethod`:
   - Use `\ReflectionClass` on `TranslatableInterface::class`.
   - Assert `$rc->hasMethod('fieldLangcode')` is true.
   - Assert the method is `public` and `abstract`.
   - Check parameters: exactly one, name `fieldName`, type `string`.
   - Check return type: `?string` (named type `string`, nullable).

```php
#[Test]
public function interfaceDeclaresFieldLangcodeMethod(): void
{
    $rc = new \ReflectionClass(TranslatableInterface::class);

    $this->assertTrue($rc->hasMethod('fieldLangcode'), 'Interface must declare fieldLangcode()');

    $method = $rc->getMethod('fieldLangcode');
    $this->assertTrue($method->isPublic(), 'fieldLangcode() must be public');
    $this->assertTrue($method->isAbstract(), 'Interface method must be abstract');

    $params = $method->getParameters();
    $this->assertCount(1, $params, 'fieldLangcode() must have exactly one parameter');
    $this->assertSame('fieldName', $params[0]->getName());
    $this->assertSame('string', (string) $params[0]->getType());

    $returnType = $method->getReturnType();
    $this->assertNotNull($returnType, 'Return type must be declared');
    $this->assertTrue($returnType->allowsNull(), 'Return type must be nullable');
    $this->assertSame('string', (string) $returnType->getName() ?? (string) $returnType);
}
```

3. Add test `traitSatisfiesFieldLangcodeViaAnonymousClass`:
   - Create an anonymous class that uses `TranslatableEntityTrait` and add the minimal
     remaining interface methods as no-op stubs so it satisfies `TranslatableInterface`.
   - The key assertion: the anonymous class is an `instanceof TranslatableInterface`.
   - Optionally call `fieldLangcode('title')` and assert the return type is `string|null`.

```php
#[Test]
public function traitSatisfiesFieldLangcodeViaAnonymousClass(): void
{
    $impl = new class implements TranslatableInterface {
        use TranslatableEntityTrait;

        // Minimal stubs for the rest of the interface.
        // TranslatableEntityTrait provides fieldLangcode() — that is the point of this test.
        public function defaultLangcode(): string { return 'en'; }
        public function activeLangcode(): string { return 'en'; }
        public function language(): string { return 'en'; }
        public function hasTranslation(string $langcode): bool { return false; }
        public function getTranslation(string $langcode): static { return $this; }
        public function addTranslation(string $langcode): static { return $this; }
        public function removeTranslation(string $langcode): void {}
        public function translations(): iterable { return []; }
        public function getTranslationLanguages(): array { return ['en']; }
    };

    $this->assertInstanceOf(TranslatableInterface::class, $impl);

    // fieldLangcode() must be callable and return string|null.
    $result = $impl->fieldLangcode('title');
    $this->assertTrue(is_string($result) || $result === null);
}
```

**Note**: If `TranslatableEntityTrait` requires entity state (e.g. a `$values` array) to
function without error, the anonymous class may need to initialize it. Inspect the trait
implementation and add the minimal initialization if needed to avoid `TypeError` or
`UninitializedError`.

**Validation**:
- [ ] `./vendor/bin/phpunit packages/entity/tests/Unit/TranslatableInterfaceContractTest.php` passes.
- [ ] Both tests green.
- [ ] `composer phpstan` passes (contract test file is clean).

---

## Definition of Done

- [ ] `TranslatableInterface` declares `fieldLangcode(string $fieldName): ?string`.
- [ ] `TranslatableEntityTrait` is unchanged (FR-009).
- [ ] `TranslatableInterfaceContractTest` passes with both tests green.
- [ ] `composer cs-check` clean.
- [ ] `composer phpstan` clean (level 5).
- [ ] Commit message includes `Closes #1447` or `Refs #1447` as appropriate.

## Risks

| Risk | Mitigation |
|------|------------|
| Anonymous class stubs miss a method added in a later PR | Run `./vendor/bin/phpunit` after editing to catch interface-not-implemented errors immediately |
| TranslatableEntityTrait requires internal state | Inspect trait before writing the stub; add `$this->values = []` or equivalent initialization |
| `ReflectionClass` return type assertion is brittle for intersection/union types | Use `allowsNull()` + string comparison on type name; do not rely on `getReturnType()` returning a `ReflectionNamedType` subclass unconditionally |

## Reviewer Guidance

- Verify the method declaration in `TranslatableInterface.php` matches the signature in `TranslatableEntityTrait` exactly (parameter name, type, return type).
- Confirm `TranslatableEntityTrait` is unmodified (diff should show zero changes to the trait file).
- Confirm `TranslatableInterfaceContractTest.php` is under `autoload-dev`, not `autoload`.
<<<<<<< HEAD

## Activity Log

- 2026-05-21T00:25:22Z – claude:sonnet:implementer:implementer – shell_pid=704409 – Assigned agent via action command
- 2026-05-21T00:39:36Z – claude:sonnet:implementer:implementer – shell_pid=704409 – Interface contract enforced; trait satisfies without changes; all existing implementors verified and stubs added; 1414 tests pass across entity/listing/access/api packages
- 2026-05-21T00:40:30Z – claude:opus-4-7:reviewer:reviewer – shell_pid=722296 – Started review via action command
- 2026-05-21T00:41:44Z – claude:opus-4-7:reviewer:reviewer – shell_pid=722296 – Review passed: interface declares fieldLangcode(string): ?string (line 107); trait signature matches at TranslatableEntityTrait.php:264; 4 direct test-only implementors stubbed with return null (RevisionPolicyCompositionTest x2, ListingCacheInvalidatorTest, ListingDefinitionValidatorTest); contract test passes (2/11); autoload clean; full sweep 1518/3387/0 across entity+access+listing+node+api.
=======
>>>>>>> kitty/mission-m006-translation-hardening-01KS3RY9-lane-a
