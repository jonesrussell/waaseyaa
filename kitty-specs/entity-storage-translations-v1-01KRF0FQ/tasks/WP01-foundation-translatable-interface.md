---
work_package_id: WP01
title: 'Foundation: TranslatableInterface + EntityTranslationException + ContentEntityBase wire-up'
dependencies: []
requirement_refs:
- FR-006
- FR-007
- FR-008
- FR-009
- FR-010
- FR-011
- FR-012
- FR-013
- FR-014
- FR-054
- FR-055
- FR-056
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T001
- T002
- T003
- T004
- T005
history: []
authoritative_surface: packages/entity/
execution_mode: code_change
owned_files:
- packages/entity/src/TranslatableInterface.php
- packages/entity/src/TranslatableEntityTrait.php
- packages/entity/src/ContentEntityBase.php
- packages/entity/src/Exception/EntityTranslationException.php
- packages/entity/tests/Unit/Translatable*
- packages/entity/tests/Unit/Exception/EntityTranslationException*
tags: []
---

# WP01 — Foundation: TranslatableInterface + EntityTranslationException + ContentEntityBase wire-up

## Objective

Ship the foundational PHP surface for single-axis translations: the expanded `TranslatableInterface`, a `TranslatableEntityTrait` that implements it, the `EntityTranslationException` exception hierarchy, and a one-line `use` addition on `ContentEntityBase`. No storage interaction yet — storage backends in WP04/WP05 populate the trait's internal state during hydration.

## Context

- **Spec:** [`../spec.md`](../spec.md) §3.2 (FR-006..FR-015), §3.11 (FR-054..FR-057)
- **Plan:** [`../plan.md`](../plan.md) §"Project Structure"
- **Research:** [`../research.md`](../research.md) R1 (naming reconciliation), R7 (testing autoload)
- **Data model:** [`../data-model.md`](../data-model.md) "Domain (PHP)"
- **Contracts:** [`../contracts/TranslatableInterface.md`](../contracts/TranslatableInterface.md)

**Critical R1 finding**: `packages/entity/src/TranslatableInterface.php` ALREADY EXISTS with a minimal stub (`language()`, `hasTranslation()`, `getTranslation()`, `getTranslationLanguages()`). Per research R1: KEEP the existing class name (do NOT introduce `TranslatableEntityInterface`); EXPAND it with the missing methods. The spec FRs reference `TranslatableEntityInterface` but the implementation uses `TranslatableInterface`. WP14 reconciles spec language with shipped reality.

**Trait pattern** (research R7 + project precedent at `packages/entity/src/RevisionableEntityTrait.php`): introduce `TranslatableEntityTrait` to hold the interface implementation. `ContentEntityBase` `use`s the trait. Future WPs (storage backends) populate the trait's protected state during hydration.

## Lane setup (one-time per lane worktree)

```bash
cd <lane-worktree>
composer install                                          # vendor/ is not symlinked into lanes
composer dump-autoload --optimize                         # PackageManifestCompiler prefers optimized classmap
```

Intelephense diagnostics in lane worktrees may show vendor-resolution noise; ignore them. Authoritative gates are the composer scripts below.

## Subtasks

### T001 — Create `EntityTranslationException` class

**Purpose:** Provide a single typed exception for all translation-related runtime failures. Static factories produce instances with formatted messages.

**Steps:**

1. Create `packages/entity/src/Exception/EntityTranslationException.php`:

   ```php
   <?php
   declare(strict_types=1);
   namespace Waaseyaa\Entity\Exception;

   final class EntityTranslationException extends \DomainException
   {
       public static function translationNotFound(string $langcode): self;
       public static function cannotRemoveDefault(string $langcode): self;
       public static function langcodeRequired(): self;
       public static function notTranslatable(string $entityTypeId): self;
       public static function translationAlreadyExists(string $langcode): self;
   }
   ```

2. Each factory returns `new self("...")` with a formatted message naming the langcode/entityTypeId. Example:
   ```php
   public static function translationNotFound(string $langcode): self
   {
       return new self(sprintf('Translation for langcode "%s" does not exist.', $langcode));
   }
   ```

3. Declare `final` to lock the shape; downstream code creates instances only via static factories.

**Files:** `packages/entity/src/Exception/EntityTranslationException.php` (new, ~50 lines).

**Validation:** Each static factory returns a `self` instance whose `getMessage()` contains the formatted langcode/typeId.

### T002 — Expand `TranslatableInterface`

**Purpose:** Bring the interface up to the full surface per spec §3.2.

**Steps:**

1. Open `packages/entity/src/TranslatableInterface.php`. The existing shape is:

   ```php
   interface TranslatableInterface
   {
       public function language(): string;
       public function getTranslationLanguages(): array;
       public function hasTranslation(string $langcode): bool;
       public function getTranslation(string $langcode): static;
   }
   ```

2. Add the following methods to the interface (full final shape):

   ```php
   interface TranslatableInterface
   {
       public function defaultLangcode(): string;
       public function activeLangcode(): string;
       public function language(): string;                              // deprecated alias for activeLangcode() — see #DEPRECATED below
       public function hasTranslation(string $langcode): bool;
       public function getTranslation(string $langcode): static;
       public function addTranslation(string $langcode): static;
       public function removeTranslation(string $langcode): void;
       public function translations(): iterable;                        // canonical name for "all langcodes"
       public function getTranslationLanguages(): array;                // alias of translations() materialized as array
   }
   ```

3. Add `@deprecated since 0.x — use activeLangcode()` PHPDoc on `language()`. PHP 8.4+ supports the `#[\Deprecated]` attribute on methods (NOT on classes; see project memory `feedback_php84_deprecated_attribute_targets.md`). Add `#[\Deprecated('Use activeLangcode() instead', '0.next')]` to the method.

4. Update the class docblock to reflect the full surface and reference ADR 017.

**Files:** `packages/entity/src/TranslatableInterface.php` (modify, ~40 lines).

**Validation:** Interface declares all 9 methods. `language()` carries the `#[\Deprecated]` attribute.

### T003 — Add `TranslatableEntityTrait`

**Purpose:** Provide a default implementation of the interface that `ContentEntityBase` (and any other future implementor) can drop in.

**Steps:**

1. Create `packages/entity/src/TranslatableEntityTrait.php`:

   ```php
   <?php
   declare(strict_types=1);
   namespace Waaseyaa\Entity;

   use Waaseyaa\Entity\Exception\EntityTranslationException;

   trait TranslatableEntityTrait
   {
       protected ?string $activeLangcode = null;

       /** @var array<string, array<string, mixed>> langcode → field-values map */
       protected array $translationData = [];

       /** @var array<string, ?string> field-name → resolved-langcode (null on fallback exhausted) */
       protected array $fieldLangcodes = [];

       /** @var array<string> langcodes marked for deletion on next save */
       protected array $pendingTranslationDeletions = [];

       public function defaultLangcode(): string
       {
           $defaultLc = $this->values['default_langcode'] ?? null;
           if ($defaultLc === null || $defaultLc === '') {
               throw EntityTranslationException::langcodeRequired();
           }
           return $defaultLc;
       }

       public function activeLangcode(): string
       {
           return $this->activeLangcode ?? $this->defaultLangcode();
       }

       public function language(): string
       {
           return $this->activeLangcode();                              // deprecated alias
       }

       public function hasTranslation(string $langcode): bool
       {
           $this->assertTranslatable();
           return isset($this->translationData[$langcode]);
       }

       public function getTranslation(string $langcode): static
       {
           $this->assertTranslatable();
           if (!$this->hasTranslation($langcode)) {
               throw EntityTranslationException::translationNotFound($langcode);
           }
           if ($langcode === $this->activeLangcode()) {
               return $this;
           }
           $clone = clone $this;
           $clone->activeLangcode = $langcode;
           return $clone;
       }

       public function addTranslation(string $langcode): static
       {
           $this->assertTranslatable();
           if ($this->hasTranslation($langcode)) {
               throw EntityTranslationException::translationAlreadyExists($langcode);
           }
           $this->translationData[$langcode] = [];                       // empty translation values
           $clone = clone $this;
           $clone->activeLangcode = $langcode;
           return $clone;
       }

       public function removeTranslation(string $langcode): void
       {
           $this->assertTranslatable();
           if ($langcode === $this->defaultLangcode()) {
               throw EntityTranslationException::cannotRemoveDefault($langcode);
           }
           if ($this->hasTranslation($langcode)) {
               $this->pendingTranslationDeletions[] = $langcode;
               unset($this->translationData[$langcode]);
           }
       }

       /** @return iterable<string> */
       public function translations(): iterable
       {
           $this->assertTranslatable();
           $default = $this->defaultLangcode();
           yield $default;
           $others = array_diff(array_keys($this->translationData), [$default]);
           sort($others);
           yield from $others;
       }

       /** @return array<int,string> */
       public function getTranslationLanguages(): array
       {
           return iterator_to_array($this->translations(), false);
       }

       public function fieldLangcode(string $fieldName): ?string
       {
           return $this->fieldLangcodes[$fieldName] ?? null;
       }

       /**
        * @internal storage-hydrator helper. Tells the trait which translations exist.
        */
       public function _setTranslationData(array $data, string $activeLangcode): void
       {
           $this->translationData = $data;
           $this->activeLangcode = $activeLangcode;
       }

       /**
        * @internal storage-coordinator helper. Returns + clears the pending deletion list.
        * @return array<string>
        */
       public function _takePendingTranslationDeletions(): array
       {
           $taken = $this->pendingTranslationDeletions;
           $this->pendingTranslationDeletions = [];
           return $taken;
       }

       private function assertTranslatable(): void
       {
           if (!$this->getEntityType()->isTranslatable()) {
               throw EntityTranslationException::notTranslatable(
                   $this->getEntityType()->id()
               );
           }
       }
   }
   ```

2. The `_setTranslationData` and `_takePendingTranslationDeletions` methods are `@internal` — storage backends (WP04/WP05) call them during hydration; the coordinator (WP07) calls the deletions taker.

**Files:** `packages/entity/src/TranslatableEntityTrait.php` (new, ~160 lines).

**Validation:** Trait compiles. All interface methods present. Internal helpers documented as `@internal`.

### T004 — Use the trait in `ContentEntityBase`

**Purpose:** Bring the full translation surface to all content entities.

**Steps:**

1. Open `packages/entity/src/ContentEntityBase.php`. The class signature is:

   ```php
   abstract class ContentEntityBase extends EntityBase implements
       ContentEntityInterface,
       HydratableFromStorageInterface
   ```

2. Modify to add `TranslatableInterface` and `use` the trait:

   ```php
   abstract class ContentEntityBase extends EntityBase implements
       ContentEntityInterface,
       HydratableFromStorageInterface,
       TranslatableInterface
   {
       use TranslatableEntityTrait;
       // ... existing body unchanged ...
   }
   ```

3. Add the `use` for `Waaseyaa\Entity\TranslatableInterface;` at the top of the file if not already imported.

4. **Method-name collision check:** verify `TranslatableEntityTrait`'s methods don't collide with existing `ContentEntityBase` methods. If a collision exists (e.g., `language()` might already be defined elsewhere), resolve via `use TranslatableEntityTrait { language as protected legacyLanguage; }` and override with the trait's `language()`. Document the collision.

**Files:** `packages/entity/src/ContentEntityBase.php` (modify, ~5 lines net).

**Validation:** `class_implements(SomeContentEntity::class)` includes `TranslatableInterface`. No method-name collisions surface at boot.

### T005 — Unit tests

**Purpose:** Verify the trait's behaviour on both translatable and non-translatable types, plus the exception factories.

**Steps:**

1. Create `packages/entity/tests/Unit/Exception/EntityTranslationExceptionTest.php`:
   - Test each static factory returns a `self` instance with a message containing the input string.

2. Create `packages/entity/tests/Unit/TranslatableEntityTraitTest.php`:
   - Set up two fixtures: `FixtureTranslatableEntity` (translatable: true) and `FixtureNonTranslatableEntity` (translatable: false).
   - Test `defaultLangcode()` returns the values from `$values['default_langcode']`.
   - Test `defaultLangcode()` throws `langcodeRequired()` when unset.
   - Test `activeLangcode()` defaults to default-langcode.
   - Test `hasTranslation()` truthy/falsy.
   - Test `getTranslation()` throws `translationNotFound()` when missing.
   - Test `addTranslation()` throws `translationAlreadyExists()` on duplicate.
   - Test `removeTranslation($defaultLc)` throws `cannotRemoveDefault()`.
   - Test `removeTranslation($otherLc)` adds to pending deletions.
   - Test `translations()` yields default first, then ascending lex.
   - Test calling any translation method on `FixtureNonTranslatableEntity` throws `notTranslatable()`.

3. Use PHPUnit 10.5 attributes: `#[Test]`, `#[CoversClass(TranslatableEntityTrait::class)]`. No `-v` flag.

**Files:** `packages/entity/tests/Unit/Exception/EntityTranslationExceptionTest.php` (new, ~50 lines), `packages/entity/tests/Unit/TranslatableEntityTraitTest.php` (new, ~250 lines).

**Validation:** All tests pass. Coverage of the trait surface ≥ 95%.

## Definition of Done

- [ ] `EntityTranslationException` class exists with 5 static factories.
- [ ] `TranslatableInterface` declares all 9 methods including `language()` with `#[\Deprecated]`.
- [ ] `TranslatableEntityTrait` implements the interface; methods throw `notTranslatable()` on non-translatable entity types.
- [ ] `ContentEntityBase` `implements TranslatableInterface` and `use`s the trait.
- [ ] Unit tests pass: `vendor/bin/phpunit packages/entity/tests/Unit/`.
- [ ] `composer phpstan` green (level 5).
- [ ] `composer cs-check` green; if it fails, run `composer cs-fix` and re-check (project memory: cs-fix may need a second pass with cleared cache — verify two consecutive clean runs).
- [ ] `bin/check-package-layers` green (no upward edges introduced).
- [ ] PHPUnit invocation does NOT use `-v` flag.

## Risks

| Risk | Mitigation |
|---|---|
| Trait method-name collisions with `ContentEntityBase`. | Resolve via `use ... as ...` rename; document the collision in WP14 reconciliation note. |
| `defaultLangcode()` throws when entity has no `default_langcode` value set (legitimate at construction time for new entities). | Constructor caller MUST set `default_langcode` for translatable types; spec FR-034 covers this at the coordinator level. WP01's job is to surface the failure, not to mask it. |
| Test fixtures need an `EntityType` definition; existing `EntityTypeManager` infra is required. | Use lightweight test fixtures that construct `EntityType` instances inline with the testing namespace. Don't pull in full kernel boot for unit tests. |

## Reviewer guidance

- Verify `TranslatableInterface` carries all 9 methods.
- Verify `language()` has `#[\Deprecated]` attribute pointing to `activeLangcode()`.
- Verify trait's `_setTranslationData()` and `_takePendingTranslationDeletions()` are documented `@internal`.
- Verify `assertTranslatable()` is `private` (not `protected` — implementers should not extend).
- Verify clone semantics in `getTranslation()` and `addTranslation()`: each returns a cloned instance with `$activeLangcode` set. The trait's internal arrays are shared by reference (correct for shared-state semantics; see data-model.md R7 note about memory NFR-003).

## Implementation command

```bash
spec-kitty agent action implement WP01 --agent <name>
```
