---
work_package_id: WP03
title: EntityType::fromClass() Factory, Constructor Cleanup, TestEntityType Helper
dependencies:
- WP02
requirement_refs:
- FR-005
- FR-007
- FR-008
- FR-010
- FR-012
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T011
- T012
- T013
- T014
- T015
- T016
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "36272"
history:
- date: '2026-04-27'
  note: Initial generation by /spec-kitty.tasks.
authoritative_surface: packages/entity/src/EntityType
execution_mode: code_change
mission_id: 01KQ6DXEQ01S6PVPT6KF5946TA
mission_slug: attribute-first-entity-definition-01KQ6DXE
owned_files:
- packages/entity/src/EntityType.php
- packages/entity/src/EntityTypeManager.php
- packages/entity/tests/Helper/TestEntityType.php
- packages/entity/tests/Unit/EntityTypeFromClassTest.php
- packages/entity/tests/Unit/Helper/TestEntityTypeTest.php
- packages/entity/tests/Unit/EntityTypeFromClassBenchmarkTest.php
- packages/entity/tests/Fixtures/AttributeFirstEntities/FactoryTestFixtures.php
tags: []
---

# WP03 — `EntityType::fromClass()`, Constructor Cleanup, TestEntityType Helper

## Branch Strategy

- **Planning base**: `main`. **Merge target**: `main`.
- **Important**: this WP is the API-break point. After merging, packages outside this WP's `owned_files` will have failing builds until WP04-WP07 catch up. Plan to land WP03 → WP04..WP07 in close sequence.

## Objective

Ship the public API change. After this WP:
- `EntityType::fromClass(string $class, ...$overrides): self` is the canonical entry point for content entity registration.
- `EntityType` constructor no longer accepts `fieldDefinitions:`.
- `EntityTypeManager::assertClassMetadataMatchesEntityType()` is gone.
- `TestEntityType::stub()` is the test-only escape hatch for shape-only test scenarios.
- Performance NFRs (5 ms first call, 0.1 ms cached) are asserted by a benchmark test.

## Context

Read these before starting:
- `kitty-specs/attribute-first-entity-definition-01KQ6DXE/spec.md` (FR-005, FR-007, FR-008, FR-012, NFR-001, NFR-002)
- `kitty-specs/attribute-first-entity-definition-01KQ6DXE/contracts/php-api.md` (full API surface)
- `packages/entity/src/EntityType.php` (current shape — `final readonly` with many constructor params)
- `packages/entity/src/EntityTypeManager.php` (currently calls `assertClassMetadataMatchesEntityType()` from `registerEntityType()`)

---

## Subtask Guidance

### T011 — Add `EntityType::fromClass()` factory

**Purpose**: The single canonical builder for content entity types.

**Steps**:
1. Open `packages/entity/src/EntityType.php`.
2. Add a static factory:
   ```php
   /**
    * Build an EntityType for a content entity class via attribute reflection.
    *
    * Reads:
    *   - #[ContentEntityType(id, label, description)]
    *   - #[ContentEntityKeys(...)]
    *   - #[Field(...)] on each public typed property
    *
    * Pass overrides for any EntityType property that isn't class-derived
    * (e.g. group, storageClass, revisionable, bundleEntityType).
    *
    * @param class-string<\Waaseyaa\Entity\ContentEntityBase> $class
    * @throws \Waaseyaa\Entity\Exception\EntityMetadataException
    */
   public static function fromClass(
       string $class,
       string $storageClass = SqlEntityStorage::class,
       bool $revisionable = false,
       bool $revisionDefault = false,
       bool $translatable = false,
       ?string $bundleEntityType = null,
       array $constraints = [],
       ?string $group = null,
   ): self;
   ```
3. Implementation:
   - Call `EntityMetadataReader::forClass($class)` → `EntityClassMetadata`.
   - If `metadata->typeId === null`, throw `EntityMetadataException` with the "must declare #[ContentEntityType]" message.
   - Call constructor with:
     - `id: $metadata->typeId`
     - `label: $metadata->label !== '' ? $metadata->label : ucfirst($metadata->typeId)`
     - `class: $class`
     - `storageClass`, `keys: $metadata->keys`, `revisionable`, `revisionDefault`, `translatable`, `bundleEntityType`, `constraints`, `group`, `description: $metadata->description !== '' ? $metadata->description : null`
     - **Internal field-definition pass-through**: see T012 for mechanism.
4. Cache the resulting `EntityType` per class (a small static `array<class-string, EntityType>` on `EntityType`).

**Files**:
- `packages/entity/src/EntityType.php` (extended; +~80 lines).

**Validation**:
- [ ] `EntityType::fromClass(SimpleFixture::class)` returns a populated instance.
- [ ] Cache: second call returns the same object (`===`).
- [ ] Override params are honored (e.g. `fromClass(X::class, group: 'content')` lands on `getGroup() === 'content'`).

---

### T012 — Remove `fieldDefinitions:` parameter from `EntityType` constructor

**Purpose**: The API break. Old call sites stop compiling.

**Steps**:
1. In `packages/entity/src/EntityType.php`:
   - Remove the public constructor parameter `array $fieldDefinitions = []`.
   - Either:
     - **Option A (recommended)**: keep the internal parameter but rename to `_fieldDefinitions` and prefix with the `@internal` PHPDoc tag. The factory and `TestEntityType::stub()` are the only callers.
     - **Option B**: introduce a private `withFieldDefinitions(array $defs): self` that returns a clone (since the class is `readonly`, it has to clone-and-replace — but `readonly` makes that tricky; `with()` patterns work via `clone` + reflection or via internal helper). Stick with Option A unless a clear benefit emerges.
2. Update all internal call sites in `packages/entity/src/` to pass `_fieldDefinitions: $metadata->fields` (only `fromClass()` and `TestEntityType::stub()` should call this).
3. Add `@internal` PHPDoc on the renamed parameter so static analyzers know it's not for app use.

**Files**:
- `packages/entity/src/EntityType.php` (modified).

**Validation**:
- [ ] Direct call `new EntityType(id: 'foo', fieldDefinitions: [...])` no longer compiles (will produce a "unknown named argument" runtime error in PHP 8.4 — confirm via a unit test that asserts `\Error` is thrown).
- [ ] `fromClass()` continues to populate the underlying field-definition state.
- [ ] `EntityType::getFieldDefinitions()` or whatever accessor exists keeps working.

**Risks**: PHP 8.4's named-argument behavior with extra/unknown args throws `\Error`. Test fixtures that pass `fieldDefinitions:` before WP04-WP07 migrate them will throw. That's expected — this is the API break point. Don't put a stop-gap in.

---

### T013 — Delete `EntityTypeManager::assertClassMetadataMatchesEntityType()` and its call site

**Purpose**: With a single source of truth via `fromClass()`, the drift validator has no purpose.

**Steps**:
1. Open `packages/entity/src/EntityTypeManager.php`.
2. Delete the private method `assertClassMetadataMatchesEntityType()` (currently around line 137-175).
3. Remove the call site in `registerEntityType()` (currently line 126).
4. Update any docblock that references the validator.

**Files**:
- `packages/entity/src/EntityTypeManager.php` (modified, deletion of ~40 lines).

**Validation**:
- [ ] `grep -rn 'assertClassMetadataMatchesEntityType' packages/` returns 0 hits.
- [ ] `EntityTypeManager::registerEntityType()` continues to function for both attribute-derived and config-entity types.

---

### T014 — Add `TestEntityType::stub()` test helper

**Purpose**: Test-only escape hatch for tests that exercise `EntityType` shape independent of any class.

**Steps**:
1. Create `packages/entity/tests/Helper/TestEntityType.php`.
2. Class declaration:
   ```php
   namespace Waaseyaa\Entity\Tests\Helper;

   use Waaseyaa\Entity\EntityType;
   use Waaseyaa\Field\FieldDefinition;

   final class TestEntityType {
       /**
        * @param array<string, FieldDefinition> $fieldDefinitions
        * @param array<string, string> $keys
        */
       public static function stub(
           string $id,
           array $fieldDefinitions = [],
           array $keys = ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label'],
           ?string $class = null,
           ?string $label = null,
       ): EntityType {
           // Use a synthetic class FQN for the EntityType.class slot if none provided
           $class ??= self::syntheticClassName($id);
           return new EntityType(
               id: $id,
               label: $label ?? ucfirst(str_replace('_', ' ', $id)),
               class: $class,
               keys: $keys,
               _fieldDefinitions: $fieldDefinitions,
           );
       }

       private static function syntheticClassName(string $id): string {
           return 'Waaseyaa\\Entity\\Tests\\Helper\\__StubEntities__\\' . str_replace([' ', '_'], '', ucwords($id, '_'));
       }
   }
   ```
3. Ensure the helper is not part of the production autoloader (lives under `tests/`).

**Files**:
- `packages/entity/tests/Helper/TestEntityType.php` (new, ~50 lines).

**Validation**:
- [ ] `TestEntityType::stub('foo', [...FieldDefinition[]])` returns an `EntityType`.
- [ ] Stubs don't pollute the `EntityType::fromClass()` cache (different code path).

---

### T015 — Tests for `fromClass()`

**Purpose**: Lock the canonical builder.

**Steps**:
1. Create `packages/entity/tests/Fixtures/AttributeFirstEntities/FactoryTestFixtures.php` with:
   - A simple entity class.
   - A class extending `ContentEntityBase` directly with no `#[ContentEntityType]` (for error tests).
   - A parent/child pair with overrides.
   - An entity class without `#[Field]` properties (empty field map case).
2. Create `packages/entity/tests/Unit/EntityTypeFromClassTest.php` covering:
   - Happy path: `fromClass(SimpleFixture::class)->id() === 'simple'`, `getFieldDefinitions()` returns the expected map.
   - Override params: `fromClass(SimpleFixture::class, group: 'content')->getGroup() === 'content'`.
   - Cache: `fromClass(SimpleFixture::class) === fromClass(SimpleFixture::class)` (identity check).
   - Inheritance: child entity's overrides are present; parent's untouched fields are inherited.
   - Error: missing `#[ContentEntityType]` throws `EntityMetadataException`.
   - Error: bad `#[Field]` usage propagates from `FieldTypeInferrer`.
   - Empty field map: entity class with no `#[Field]` returns `EntityType` with empty `getFieldDefinitions()`.

3. Create `packages/entity/tests/Unit/Helper/TestEntityTypeTest.php`:
   - `stub('foo', [...])` returns expected `EntityType`.
   - `stub` accepts custom keys map.
   - `stub` accepts custom `class` parameter.

**Files**:
- `packages/entity/tests/Fixtures/AttributeFirstEntities/FactoryTestFixtures.php` (new, ~50 lines).
- `packages/entity/tests/Unit/EntityTypeFromClassTest.php` (new, ~200 lines).
- `packages/entity/tests/Unit/Helper/TestEntityTypeTest.php` (new, ~80 lines).

**Validation**:
- [ ] All scenarios green.
- [ ] No test depends on production entity classes (those migrate in WP04+).

---

### T016 — Performance benchmark test (NFR-001 / NFR-002)

**Purpose**: Assert the performance budget.

**Steps**:
1. Create `packages/entity/tests/Unit/EntityTypeFromClassBenchmarkTest.php`.
2. The fixture entity should have ≥ 12 typed properties decorated with `#[Field]` (mix of types) — use one of the larger `FactoryTestFixtures` entities.
3. Test 1 — first-call timing:
   ```php
   public function testFirstCallUnderFiveMilliseconds(): void {
       EntityMetadataReader::clearCache();
       $start = hrtime(true);
       EntityType::fromClass(BenchmarkFixture::class);
       $elapsedMs = (hrtime(true) - $start) / 1_000_000;
       $this->assertLessThan(5.0, $elapsedMs, "First call took {$elapsedMs} ms; budget is 5 ms.");
   }
   ```
4. Test 2 — cached-call timing:
   ```php
   public function testCachedCallUnderHundredMicroseconds(): void {
       EntityType::fromClass(BenchmarkFixture::class); // warm
       $start = hrtime(true);
       for ($i = 0; $i < 1000; $i++) {
           EntityType::fromClass(BenchmarkFixture::class);
       }
       $perCallMs = ((hrtime(true) - $start) / 1_000_000) / 1000;
       $this->assertLessThan(0.1, $perCallMs, "Cached call avg {$perCallMs} ms; budget is 0.1 ms.");
   }
   ```
5. Mark the test class with a PHPUnit group `#[Group('benchmark')]` so CI can opt out on noisy runners.
6. Document in the test class docblock that NFR-001 / NFR-002 are the source.

**Files**:
- `packages/entity/tests/Unit/EntityTypeFromClassBenchmarkTest.php` (new, ~100 lines).

**Validation**:
- [ ] Test passes locally on a development machine (developer's runs); CI may opt out via group filter.
- [ ] No flakiness — use 1000-iteration averaging for the cached test.

---

## Definition of Done

- All 6 subtask checkboxes ticked.
- `vendor/bin/phpunit packages/entity/tests/` green for the new test files.
- `grep -rn 'assertClassMetadataMatchesEntityType' packages/` returns 0.
- `grep -rn 'fieldDefinitions:' packages/entity/src/` returns 0 (the public constructor param is gone; only the internal `_fieldDefinitions` remains, used by `fromClass` and `stub`).
- No file outside `owned_files` modified.
- **Expected red state**: After this WP merges, packages outside this WP's owned_files (specifically all 45 sites listed in tasks.md) will have failing tests until WP04-WP07 catch up. This is by design.

## Risks

- **PHP 8.4 named-arg behavior**: passing an unknown named argument via `new EntityType(fieldDefinitions: [...])` post-removal will throw `\Error`. Tests that expected the old behavior in WP04-WP07 will fail until migrated. That's correct.
- **`final readonly` and the with-pattern**: keeping `_fieldDefinitions` as an internal constructor param avoids cloning gymnastics. Don't fight `readonly` here.
- **Cache identity**: `EntityType::fromClass()` cache uses `===` semantics. If callers pass overrides, the cache key needs to include the override values OR caller-side responsibility is "for a given class, always use the same overrides". Implementation suggestion: cache by class name only; reject calls with conflicting overrides via comparison; keep it simple by caching by class name and trusting consumers to be consistent (single static registration per class is the framework norm anyway).

## Reviewer guidance

- Verify `fromClass()` builds a complete `EntityType` from a fixture class with no further plumbing.
- Verify `TestEntityType::stub()` doesn't go through the cache and doesn't reflect on a class.
- Confirm the validator deletion is clean (no orphan tests).
- Run benchmarks — expect ~1-2 ms first call, ~0.01 ms cached.

## Implementation command

```
spec-kitty agent action implement WP03 --agent <name>
```

## Activity Log

- 2026-04-27T04:04:17Z – claude:opus-4-7:implementer:implementer – shell_pid=15612 – Started implementation via action command
- 2026-04-27T04:13:39Z – claude:opus-4-7:implementer:implementer – shell_pid=15612 – Ready for review: EntityType::fromClass factory, constructor cleanup, TestEntityType stub, benchmark NFRs asserted
- 2026-04-27T04:14:30Z – claude:opus-4-7:reviewer:reviewer – shell_pid=36272 – Started review via action command
- 2026-04-27T04:16:01Z – claude:opus-4-7:reviewer:reviewer – shell_pid=36272 – Review passed: fromClass factory + cache work, fieldDefinitions: removed from public surface, validator deleted, TestEntityType stub bypasses cache, NFR-001/002 benchmarks green, WP01+WP02 50/50 + new 17/17 tests pass, PHPStan clean
- 2026-04-27T06:13:08Z – claude:opus-4-7:reviewer:reviewer – shell_pid=36272 – Done override: Mission merged in ce123bfe
