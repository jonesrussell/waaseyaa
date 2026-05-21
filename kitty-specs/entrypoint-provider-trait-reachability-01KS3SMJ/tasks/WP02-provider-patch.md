---
work_package_id: WP02
title: Provider Patch — Add isTraitWithApiPhpDoc and Unit Tests
dependencies:
- WP01
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-006
- FR-007
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-entrypoint-provider-trait-reachability-01KS3SMJ
base_commit: e4620a19d4e6acaa7df15a9b9c48af6bdb44cf0b
created_at: '2026-05-21T00:46:39.695794+00:00'
subtasks:
- T006
- T007
- T008
shell_pid: "739599"
agent: "claude:sonnet:implementer:implementer"
history:
- date: '2026-05-20T23:57:25Z'
  author: tasks-materializer
  note: Initial WP file created
authoritative_surface: tools/phpstan/
execution_mode: code_change
owned_files:
- tools/phpstan/WaaseyaaEntrypointProvider.php
- tools/phpstan/tests/WaaseyaaEntrypointProviderTest.php
tags: []
---

# WP02 — Provider Patch: Add isTraitWithApiPhpDoc and Unit Tests

## Branch Strategy

- **Planning/base branch**: `main`
- **Merge target**: `main`
- **Pre-condition**: WP01's `research/wp01-diagnosis.md` must be committed and approved before starting this WP.
- **Worktree**: Allocated from `lanes.json` at runtime. Run `spec-kitty agent action implement WP02 --agent <name>` to enter the lane.

## Objective

Based on WP01's confirmed diagnosis, extend `WaaseyaaEntrypointProvider` with a narrowly-scoped fix that makes class-level `@api` on a trait propagate to all its members. Add the single named method `isTraitWithApiPhpDoc` and wire it into the three `shouldMark*` methods. Write unit tests (FR-006). Commit closes #1501.

## Context

**Read WP01's `research/wp01-diagnosis.md` first.** The exact implementation path depends on the confirmed hypothesis:

- **If hypothesis (b) or (c)**: The primary fix is adding `isTraitWithApiPhpDoc` called from `shouldMarkPropertyAsRead/Written` and `shouldMarkMethodAsUsed`. The `hasApiPhpDoc` check already works for the trait's own reflection — the gap is that there's no dedicated early-return for trait-typed declaring classes.
- **If hypothesis (a) (scanner miss)**: The `loadEntitySupportingTraits` glob also needs widening (add `packages/*/src/**/*.php` recursion or add the specific entity subdirectory). The `isTraitWithApiPhpDoc` path still covers the testing traits (which are never in entity scan scope).
- **If hypothesis (d) (mixed)**: Implement both fixes.

The plan assumes (b)/(c) is most likely based on provider code analysis, but **the implementation MUST follow WP01's confirmed hypothesis**.

## Key files

- `tools/phpstan/WaaseyaaEntrypointProvider.php` — the only source file to edit
- `tools/phpstan/tests/WaaseyaaEntrypointProviderTest.php` — new file (no existing test directory)
- `phpstan.neon` or `phpstan-dead-code.neon` — check if test directory needs inclusion (likely yes for unit testing the provider)

---

## Subtask T006 — Add `isTraitWithApiPhpDoc()` named method

**Purpose**: Implement the single named method that makes trait-`@api` propagation explicit, testable, and documented. FR-007 requires this named method so future contributors can find where trait `@api` propagation lives.

**Steps**:

1. Open `tools/phpstan/WaaseyaaEntrypointProvider.php`.

2. Add the following private static method after `hasApiPhpDoc` (line ~155):

   ```php
   /**
    * Returns true when the declaring class is a trait that carries a class-level
    * `@api` docblock. In that case every property and method declared by the
    * trait is treated as a Waaseyaa entrypoint: reflection-based hydration
    * (ReflectionProperty::setValue, ContentEntityBase::set()) and test-surface
    * consumption are invisible to AST-level call-graph analysis.
    *
    * This is the canonical propagation path for @api on traits. Adding @api to
    * a trait's class docblock is sufficient — no per-trait registration needed.
    */
   private static function isTraitWithApiPhpDoc(\ReflectionClass $declaringClass): bool
   {
       return $declaringClass->isTrait() && self::hasApiPhpDoc($declaringClass);
   }
   ```

3. This method must be the named, documented home for trait `@api` propagation (FR-007). Do not inline the logic.

**Validation**:
- [ ] Method exists at class scope with correct signature.
- [ ] PHPDoc explains the propagation purpose and names the three original traits.
- [ ] `cs-fix` reports no style violations.

---

## Subtask T007 — Wire isTraitWithApiPhpDoc into the three shouldMark* methods

**Purpose**: Apply the early-return guard in all three callbacks so trait members are marked as used before the more expensive `isEntrypointClass` check runs.

**Steps**:

1. **`shouldMarkPropertyAsRead`** (lines 90–95): Add an early return before the `isEntrypointClass` call:

   ```php
   protected function shouldMarkPropertyAsRead(ReflectionProperty $property): ?VirtualUsageData
   {
       if (self::isTraitWithApiPhpDoc($property->getDeclaringClass())) {
           return VirtualUsageData::withNote('Waaseyaa trait @api entrypoint');
       }

       return $this->isEntrypointClass($property->getDeclaringClass()->getName(), $property->getDeclaringClass())
           ? VirtualUsageData::withNote('Waaseyaa entrypoint property')
           : null;
   }
   ```

2. **`shouldMarkPropertyAsWritten`** (lines 97–100): This already delegates to `shouldMarkPropertyAsRead` — no additional change needed since the early return in `shouldMarkPropertyAsRead` will fire.

   Verify by inspection. If `shouldMarkPropertyAsWritten` duplicates logic instead of delegating, add the same early return there too.

3. **`shouldMarkMethodAsUsed`** (lines 71–81): Add the early return before the controller-ref check:

   ```php
   protected function shouldMarkMethodAsUsed(ReflectionMethod $method): ?VirtualUsageData
   {
       if (self::isTraitWithApiPhpDoc($method->getDeclaringClass())) {
           return VirtualUsageData::withNote('Waaseyaa trait @api entrypoint');
       }

       $ref = $method->getDeclaringClass()->getName() . '::' . $method->getName();
       if (isset($this->controllerMethodRefs[$ref])) {
           return VirtualUsageData::withNote('Waaseyaa route controller (string ref)');
       }

       return $this->isEntrypointClass($method->getDeclaringClass()->getName(), $method->getDeclaringClass())
           ? VirtualUsageData::withNote('Waaseyaa entrypoint (policy/middleware/provider/mapper/route-provider)')
           : null;
   }
   ```

4. **`shouldMarkConstantAsUsed`** (lines 83–88): Traits rarely have constants, but for completeness add the same early return if WP01's diagnosis shows constant entries. If WP01's inventory shows no constant findings, skip.

5. **If hypothesis (a) was confirmed (scanner miss)**: Also widen `loadEntitySupportingTraits` globs. Add:
   ```php
   foreach (glob($packagesDir . '/*/src/**/*.php') ?: [] as $file) {
       $candidates[] = $file;
   }
   ```
   after the existing two glob calls (lines 230–235). This handles entity classes nested deeper than `src/Entity/`. Note: `glob()` with `**` is not recursive on all PHP versions without `GLOB_BRACE`; use `RecursiveDirectoryIterator` if needed. Check WP01's diagnosis for the specific missing path.

6. Run code style fix:
   ```bash
   composer cs-fix
   ```

**Validation**:
- [ ] `shouldMarkPropertyAsRead` has the early return.
- [ ] `shouldMarkMethodAsUsed` has the early return.
- [ ] `shouldMarkPropertyAsWritten` either delegates (already correct) or also has the early return.
- [ ] `composer cs-check` exits 0.
- [ ] `composer phpstan` exits 0 (no new type errors introduced).

---

## Subtask T008 — Write WaaseyaaEntrypointProviderTest.php

**Purpose**: Unit tests covering all three FR-006 cases. Verify the fix works before the expensive baseline regeneration in WP03.

**Test file location**: `tools/phpstan/tests/WaaseyaaEntrypointProviderTest.php`

**Steps**:

1. Create the directory if needed: `mkdir -p tools/phpstan/tests`

2. Write the test file. Key structure:

   ```php
   <?php declare(strict_types=1);

   namespace Waaseyaa\Tools\PHPStan\Tests;

   use PHPUnit\Framework\TestCase;
   use ReflectionProperty;
   use ReflectionMethod;
   use Waaseyaa\Tools\PHPStan\WaaseyaaEntrypointProvider;

   /**
    * @covers \Waaseyaa\Tools\PHPStan\WaaseyaaEntrypointProvider
    */
   class WaaseyaaEntrypointProviderTest extends TestCase
   {
       private WaaseyaaEntrypointProvider $provider;

       protected function setUp(): void
       {
           // Pass repo root so loadDeclaredProviders/loadEntitySupportingTraits work
           $this->provider = new WaaseyaaEntrypointProvider(
               dirname(__DIR__, 3) // tools/phpstan/tests → repo root
           );
       }

       // (a) Fixture trait with class-level @api → property marked as read
       public function testTraitWithApiAnnotationMarkesPropertyAsRead(): void { … }

       // (b) Fixture trait without @api → property NOT marked as read
       public function testTraitWithoutApiAnnotationDoesNotMarkPropertyAsRead(): void { … }

       // (c) Regression: RevisionableEntityTrait property marked as read
       public function testRevisionableEntityTraitPropertyIsMarkedAsRead(): void { … }

       // (c) Regression: InteractsWithApi method marked as used
       public function testInteractsWithApiMethodIsMarkedAsUsed(): void { … }

       // (c) Regression: RefreshDatabase method marked as used
       public function testRefreshDatabaseMethodIsMarkedAsUsed(): void { … }
   }
   ```

3. **Fixture trait approach for (a) and (b)**: Use a temporary PHP file written to `sys_get_temp_dir()`, included once:

   ```php
   private function createFixtureTrait(string $traitName, bool $withApiAnnotation): string
   {
       $annotation = $withApiAnnotation ? " * @api\n" : '';
       $code = <<<PHP
       <?php
       /**
        * {$annotation}*/
       trait {$traitName} {
           public string \$fixtureProperty = '';
           public function fixtureMethod(): void {}
       }
       PHP;
       $file = sys_get_temp_dir() . '/WP02_' . $traitName . '.php';
       file_put_contents($file, $code);
       require_once $file;
       return $traitName;
   }
   ```

   Then: `$reflection = new \ReflectionClass($traitName)` and get the property/method from it.

4. **Calling protected methods**: The provider's `shouldMarkPropertyAsRead` and `shouldMarkMethodAsUsed` are `protected`. Use a test subclass or `ReflectionMethod` to call them:

   ```php
   private function invokePropertyRead(ReflectionProperty $property): mixed
   {
       $method = new \ReflectionMethod($this->provider, 'shouldMarkPropertyAsRead');
       $method->setAccessible(true);
       return $method->invoke($this->provider, $property);
   }
   ```

5. **Regression test (c)** for real traits:

   ```php
   public function testRevisionableEntityTraitPropertyIsMarkedAsRead(): void
   {
       $trait = new \ReflectionClass(\Waaseyaa\Entity\RevisionableEntityTrait::class);
       $properties = $trait->getProperties();
       $this->assertNotEmpty($properties, 'RevisionableEntityTrait should declare properties');
       $result = $this->invokePropertyRead($properties[0]);
       $this->assertNotNull($result, 'Property from @api trait should be marked as read');
   }
   ```

   Repeat for `InteractsWithApi` (using a method via `invokeMethodUsed`) and `RefreshDatabase`.

6. Ensure the test file is in a directory discoverable by PHPUnit. Check `phpunit.xml` for test suite configuration. If `tools/phpstan/tests/` is not listed, either add it or note in WP04 that it should be added. For now, run with explicit path:
   ```bash
   vendor/bin/phpunit tools/phpstan/tests/WaaseyaaEntrypointProviderTest.php
   ```

7. Run the tests:
   ```bash
   vendor/bin/phpunit tools/phpstan/tests/WaaseyaaEntrypointProviderTest.php
   ```
   All tests must pass before committing.

8. Commit both files (`WaaseyaaEntrypointProvider.php` + test file). Commit footer must include `Closes #1501`.

   ```
   fix(dead-code): add isTraitWithApiPhpDoc to WaaseyaaEntrypointProvider

   Class-level @api on a trait now propagates to all its properties and methods
   via the new isTraitWithApiPhpDoc() method. Covers RevisionableEntityTrait (17
   entries), InteractsWithApi (9 entries), and RefreshDatabase (5 entries).

   Closes #1501
   ```

**Validation**:
- [ ] `tools/phpstan/tests/WaaseyaaEntrypointProviderTest.php` exists with 5 test methods.
- [ ] All 5 tests pass.
- [ ] Test (a): fixture trait with `@api` returns non-null `VirtualUsageData`.
- [ ] Test (b): fixture trait without `@api` returns `null`.
- [ ] Tests (c): all three real traits return non-null.
- [ ] Commit footer contains `Closes #1501`.
- [ ] `composer phpstan` exits 0 (no PHPStan errors in the test file itself).

---

## Definition of Done

- `tools/phpstan/WaaseyaaEntrypointProvider.php` has `isTraitWithApiPhpDoc()` method with full PHPDoc.
- `shouldMarkPropertyAsRead`, `shouldMarkPropertyAsWritten`, and `shouldMarkMethodAsUsed` all guard via `isTraitWithApiPhpDoc`.
- `tools/phpstan/tests/WaaseyaaEntrypointProviderTest.php` exists with ≥5 tests, all passing.
- `composer cs-check` exits 0.
- `composer phpstan` exits 0.
- All tests pass via `vendor/bin/phpunit tools/phpstan/tests/WaaseyaaEntrypointProviderTest.php`.
- Commit includes `Closes #1501`.

## Risks

- `shouldMarkPropertyAsWritten` delegates to `shouldMarkPropertyAsRead` already — verify this rather than assuming, to avoid duplicate early returns.
- Protected method access in tests: use ReflectionMethod + setAccessible(true).
- If hypothesis (a) is confirmed and glob widening is needed, `glob()` with `**` may not work on all platforms — use `RecursiveDirectoryIterator` as a safer alternative.
- Temp-file fixture traits may collide across test runs if names are not unique — prefix with a random suffix or use `uniqid()`.

## Reviewer Guidance

- Verify `isTraitWithApiPhpDoc` is a named, documented method (not inlined logic) — FR-007.
- Verify the method docblock explains the trait `@api` propagation purpose.
- Verify all three `shouldMark*` methods have the early return.
- Verify test cases (a) and (b) use different fixture traits (not the same trait toggling a flag).
- Verify regression test (c) uses real reflection of the actual trait classes.
- Confirm `Closes #1501` is in the commit footer.

## Activity Log

- 2026-05-21T00:46:41Z – claude:sonnet:implementer:implementer – shell_pid=739599 – Assigned agent via action command
