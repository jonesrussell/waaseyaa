---
work_package_id: WP02
title: 'F2: Bundle/Field-template attributes + compiler'
dependencies:
- WP01
requirement_refs:
- FR-003
- FR-004
- NFR-003
- NFR-005
- NFR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Plan from main; lane execution branch is allocated by finalize-tasks via lanes.json. Final merge target is main.
subtasks:
- T005
- T006
- T007
- T008
- T009
- T010
history:
- date: '2026-04-27'
  note: Generated from plan.md + research.md + data-model.md + contracts/.
authoritative_surface: packages/field/src/Attribute/
execution_mode: code_change
mission_id: 01KQ7M1PHWD8QAQPJC91RAVE0T
mission_slug: single-entity-work-surface-01KQ7M1P
owned_files:
- packages/field/src/Attribute/BundleTemplate.php
- packages/field/src/Attribute/FieldTemplate.php
- packages/field/src/BundleTemplateCompiler.php
- packages/field/src/FieldServiceProvider.php
- packages/field/tests/Unit/BundleTemplateCompilerTest.php
- packages/field/tests/Integration/BundleTemplateRegistrationTest.php
- packages/field/tests/Fixtures/Templates/**
tags: []
---

# WP02 — F2: Bundle/Field-template attributes + compiler

## Objective

Ship the attribute-driven bundle-template registration path. Add two PHP 8.4 attributes (`BundleTemplate` class-level, `FieldTemplate` repeatable on properties/methods) and a `BundleTemplateCompiler` that scans for them and registers `FieldDefinition` instances with the existing `FieldDefinitionRegistry::registerBundleFields()`. Wire the compiler at boot.

This is the F2 primitive: one declarative source feeds both F6 (form descriptors) and F5 (importer prompt matching), honoring CLAUDE.md's "single source of truth" rule.

## Context (read first)

- **spec.md** FR-003, FR-004 — attribute-driven registration semantics.
- **research.md** Q1 (FieldDefinition signature available), Q2 (PolicyAttribute discovery pattern is the reference for our scanning).
- **data-model.md § 3** — exact attribute signatures and compiler contract.
- **contracts/README.md** F2 — acceptance criteria for the compiler.
- **`packages/field/src/FieldDefinitionRegistry.php`** at line 113 — `registerBundleFields($entityTypeId, $bundle, array $fields)` is the registration surface. Don't bypass it.
- **`packages/foundation/src/Manifest/PackageManifestCompiler.php`** — existing attribute-discovery infrastructure. Reuse its class-scanning path; do not invent a new attribute scanner.

## Branch Strategy

- **Planning base**: `main` (after WP01 lands)
- **Final merge target**: `main`
- Lane allocation via `finalize-tasks`. Use `spec-kitty agent action implement WP02 --agent <name> --mission single-entity-work-surface-01KQ7M1P` to enter the workspace.

## Subtasks

### T005 — `BundleTemplate` class attribute

**File**: `packages/field/src/Attribute/BundleTemplate.php`

```php
<?php
declare(strict_types=1);

namespace Waaseyaa\Field\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class BundleTemplate
{
    public function __construct(
        public string $entityType,
        public string $bundle,
    ) {}
}
```

**Validation**: PHPStan passes; class is `final readonly` PHP 8.4 idiom.

### T006 — `FieldTemplate` repeatable attribute

**File**: `packages/field/src/Attribute/FieldTemplate.php`

```php
<?php
declare(strict_types=1);

namespace Waaseyaa\Field\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class FieldTemplate
{
    /** @param list<string> $promptAliases */
    public function __construct(
        public string $key,
        public string $type,
        public string $label = '',
        public string $group = '',
        public array $promptAliases = [],
        public bool $required = false,
        public bool $readOnly = false,
    ) {}
}
```

**Validation**: PHPStan passes; attribute is repeatable so multiple `#[FieldTemplate]` on one method/property work.

### T007 — `BundleTemplateCompiler`

**File**: `packages/field/src/BundleTemplateCompiler.php`

**Purpose**: Discover classes with `#[BundleTemplate]`, read each class's `#[FieldTemplate]` attributes (in declaration order: properties first by order, then methods by order), build `FieldDefinition` instances, and register them via `FieldDefinitionRegistry::registerBundleFields()`.

**Steps**:

1. Constructor: inject `FieldDefinitionRegistryInterface` (rename or alias if the existing concrete class isn't behind an interface — check `packages/field/src/FieldDefinitionRegistry.php` for an existing interface). Also inject the existing manifest/class-scanner.

2. `compile(): void` method that:
   - Iterates over discovered classes with the `BundleTemplate` attribute (reuse `PackageManifestCompiler`'s class scan; check `Waaseyaa\Foundation\Manifest\PackageManifestCompiler` for the existing API).
   - For each class:
     - Read `BundleTemplate` instance (one per class).
     - Use `\ReflectionClass` to enumerate properties (in declaration order) and methods (in declaration order). For each, collect all `#[FieldTemplate]` attributes (repeatable).
     - Build `FieldDefinition` for each `FieldTemplate`:
       ```php
       new FieldDefinition(
           name: $tpl->key,
           type: $tpl->type,
           label: $tpl->label,
           required: $tpl->required,
           readOnly: $tpl->readOnly,
           targetEntityTypeId: $bundleTpl->entityType,
           targetBundle: $bundleTpl->bundle,
           group: $tpl->group,
           promptAliases: $tpl->promptAliases,
       );
       ```
   - Validate within each `(entityType, bundle)`:
     - No duplicate field `key`. Throw `\InvalidArgumentException("Duplicate field key 'X' in bundle 'node:profile'")`.
     - No duplicate normalized `promptAlias`. Use `Waaseyaa\StructuredImport\Gfm\PromptNormalizer::normalize()` if available, else inline the same logic (`mb_strtolower` + `\s+/u` collapse + `trim`). **NOTE**: WP02 ships before WP09 (where `PromptNormalizer` lives). Inline the normalization here as a private method; the duplication is acceptable, both will use the same algorithm. WP10 may consolidate later.
   - Call `$registry->registerBundleFields($entityType, $bundle, $fieldDefinitions)`.

3. Idempotency: cache the compiled state. Re-running `compile()` should be safe (no double-registration). Use a flag on the compiler instance, or rely on the registry to throw on duplicate registration and catch+log.

**Validation**:
- Compiler is invokable as a service.
- Reflection-based attribute reading is correct for class + property + method targets.
- Validation errors include the entity_type/bundle/key context.

### T008 — Wire compiler at boot

**File**: `packages/field/src/FieldServiceProvider.php` (NEW — the field package currently has no ServiceProvider)

**Steps**:

1. Create `FieldServiceProvider extends ServiceProvider`. Look at `packages/access/src/AccessServiceProvider.php` or any peer L1 package's provider as a reference shape.

2. In `register()`:
   - Bind `FieldDefinitionRegistry` (or its interface) as a singleton.
   - Bind `FieldTypeManager` as a singleton (if not already wired elsewhere).
   - Bind `BundleTemplateCompiler` resolving its registry dep.
   - Bind `FormDescriptorBuilder` (forward reference — class created in WP03; this is allowed because PHP resolves bindings at runtime).

3. In `boot()`:
   - Resolve `BundleTemplateCompiler` and call `compile()`.

4. Add `extra.waaseyaa.providers` entry in `packages/field/composer.json` listing the provider class for auto-discovery.

**Validation**:
- Booting the kernel triggers `compile()` once.
- The provider's class shows up in `PackageManifest`'s discovered providers.
- Existing tests in `packages/field/tests/` still pass (no regression).

### T009 — Compiler unit tests

**File**: `packages/field/tests/Unit/BundleTemplateCompilerTest.php`

**Cases**:
- Single bundle with three `#[FieldTemplate]` properties → registry has three fields in declaration order with the declared metadata.
- Bundle with one method-decorated `#[FieldTemplate]` and two property-decorated → registry has all three; method-decorated fields ordered after properties.
- Two `#[FieldTemplate]` repeated on the same property → registry has both as separate fields.
- Duplicate `key` within a bundle → `\InvalidArgumentException`.
- Duplicate normalized `promptAlias` within a bundle → `\InvalidArgumentException`.
- Class without `#[BundleTemplate]` is ignored.
- Two classes both with `#[BundleTemplate]` for the same `(entity_type, bundle)` → fields merge in scan order; collision in `key` throws.
- Calling `compile()` twice in a row is idempotent (no double-registration error).

Use a stub `FieldDefinitionRegistry` that records calls; assert directly against the recorded payload.

### T010 — Integration test

**File**: `packages/field/tests/Integration/BundleTemplateRegistrationTest.php`

**Fixtures**: create `packages/field/tests/Fixtures/Templates/SampleArticleTemplate.php` with `#[BundleTemplate(entityType: 'node', bundle: 'article')]` and 3 `#[FieldTemplate]` properties (matches the data-model.md § 3 example).

**Cases**:
- Boot a minimal kernel with `FieldServiceProvider` registered.
- Assert `FieldDefinitionRegistry::bundleFieldsFor('node', 'article')` returns 3 `FieldDefinition` instances.
- Assert each field's `getLabel()`, `getGroup()`, `getPromptAliases()` match the declared `#[FieldTemplate]` values.

## Definition of Done

- [ ] `BundleTemplate` and `FieldTemplate` attributes exist and PHPStan-clean.
- [ ] `BundleTemplateCompiler::compile()` discovers attributes and registers fields via `FieldDefinitionRegistry::registerBundleFields()`.
- [ ] Compiler validates uniqueness within `(entity_type, bundle)`: keys, normalized aliases. Errors include identifying context.
- [ ] `FieldServiceProvider` wires the compiler in `boot()`.
- [ ] `composer.json` declares the provider in `extra.waaseyaa.providers`.
- [ ] Compiler unit tests cover discovery, ordering, repeatable attributes, validation errors, idempotency.
- [ ] Integration test passes against a minimal kernel boot.
- [ ] `composer phpstan`, `composer cs-check`, full PHPUnit suite pass.
- [ ] `bin/check-package-layers` passes (no new upward dependencies).
- [ ] No code changes outside the `owned_files` list.

## Risks

| Risk | Mitigation |
|---|---|
| `PackageManifestCompiler` API doesn't expose attribute discovery as a reusable hook | Read its source; if no public hook exists, use `\ReflectionClass` directly against `PackageManifest::getClasses()` (or equivalent). Do not duplicate scan logic — reuse the existing class enumeration. |
| Adding a `FieldServiceProvider` may collide with how field types are currently wired | Inspect `packages/field/composer.json` and any `extra.waaseyaa.providers` entries today. If the field package already has a provider, edit it instead of creating a new file (and update owned_files accordingly via WP-prompt amendment). |
| `PromptNormalizer` duplication between WP02 and WP09 | Acceptable per DIR-003 "no premature abstraction" — two ~6-line methods. WP10 may extract a shared utility if the pattern proliferates. |
| Compiler registers on every kernel boot, exceeding NFR-003 (5 ms for 100 bundles × 10 fields) | Cache compiled state in `PackageManifest`'s persisted manifest if hot-path performance is observed. For first cut, in-memory cache + boot-time-once is sufficient. |

## Reviewer guidance

- Verify both attributes use PHP 8.4 `final readonly class` form. Reject if not.
- Verify the compiler resolves `FieldDefinitionRegistry` through the registry interface, not the concrete class (per DI hygiene).
- Verify the validation errors include `(entity_type, bundle)` context — bare "Duplicate key 'foo'" is unhelpful.
- Verify there are no `@deprecated` annotations or `Legacy*` references — DIR-003 forbids them.
- Confirm CHANGELOG.md is **not** edited (deferred to WP10).

## Implementation command

```bash
spec-kitty agent action implement WP02 --agent <agent-name> --mission single-entity-work-surface-01KQ7M1P
```

Depends on WP01 being approved.
