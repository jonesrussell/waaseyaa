---
work_package_id: WP10
title: ListingDefinitionValidator + UnsupportedListingException raise paths + boot failure integration test
dependencies:
- WP02
- WP05
requirement_refs:
- FR-050
- FR-051
- FR-052
- FR-053
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T047
- T048
- T049
- T050
history: []
authoritative_surface: packages/listing/
execution_mode: code_change
owned_files:
- packages/listing/src/ListingDefinitionValidator.php
- packages/listing/tests/Unit/ListingDefinitionValidatorTest.php
- tests/Integration/Phase14/BootValidationFailureTest.php
tags: []
agent: "claude:opus:python-reviewer:reviewer"
shell_pid: "45049"
---

## Objective

Build the boot-time validator that catches misconfigured listings before they reach production traffic. Validates every registered `ListingDefinition` against the entity-type / field / backend constraints in spec §3.13. Throws `UnsupportedListingException` on first failure (fail-fast). Kernel refuses to boot on invalid definitions.

## Context

- Validation timing: per R-02 / FR-052, runs in `PackageManifestCompiler::warm()` post entity-type registration, pre-route dispatch. Dev: every request. Prod: only on manifest rebuild.
- Failure mode: fail-fast. Kernel boot raises the exception; not caught silently.
- Per `contracts/listing-definition.md`, boot-time invariants cover 8 rule families.

## Subtask details

### T047 — `ListingDefinitionValidator` class

**Steps:**
1. Create `packages/listing/src/ListingDefinitionValidator.php`:
   - `final class ListingDefinitionValidator`
   - Constructor: `__construct(private readonly EntityTypeManager $entityTypes)`
   - Method `public function validate(ListingDefinitionRegistry $registry): void` — fail-fast per FR-053
2. Validate each registered definition:
   - **Rule A**: `pageSize > 1000` AND `!isUnbounded()` → `UnsupportedListingException($id, null, 'pageSize exceeds 1000 without allowUnbounded()')`
   - **Rule B**: `pageSize === null` AND `!isUnbounded()` → `UnsupportedListingException($id, null, 'pageSize is null without allowUnbounded()')`
   - **Rule C**: `approximateTotal === true` AND `pageSize === null` AND `isUnbounded()` → `UnsupportedListingException($id, null, 'approximateTotal=true with allowUnbounded() has no useful semantics')`
   - **Rule D**: Entity type must exist:
     - `$this->entityTypes->has($def->entityType)` else throw with `reason: 'entity type {entityType} not registered'`
   - **Rule E**: Bundle (if set) must exist:
     - `$entityType->hasBundle($def->bundle)` else throw with `reason: 'bundle {bundle} not registered for entity type {entityType}'`
   - **Rule F**: Every filter/sort field must exist on the entity type:
     - For each `FilterDefinition`/`SortDefinition`, check `$entityType->hasField($f->field)`
     - On miss: throw with `fieldName: $f->field, reason: 'field not declared'`
   - **Rule G**: Every filter/sort field's backend must support query:
     - `$fieldStorage->supportsQuery() === true` else throw with `reason: 'field {field} backend reports supportsQuery=false'`
   - **Rule H**: Operator-to-field-type compatibility:
     - For each filter, check `$fieldType` (string/int/bool/date/etc.) matches `$op`:
       - `BETWEEN` requires comparable type (int/float/date)
       - `STARTS_WITH`/`CONTAINS` require string type
       - `IN`/`NOT_IN` value array elements must each match the field type
     - On mismatch: throw with `reason: 'operator {op} incompatible with field type {type}'`
   - **Rule I**: Langcode filter only on translatable types:
     - For each filter with `field === 'langcode'`, check `$entityType->isTranslatable() === true`
     - On non-translatable: throw with `reason: 'langcode filter on non-translatable entity type'`

**Files:** `packages/listing/src/ListingDefinitionValidator.php` (new, ~180 lines).

### T048 — Raise paths

**Purpose:** Every rule above raises a typed exception with full context.

**Steps:**
1. Verify every throw site:
   - Carries `listingId` (the failing listing's id)
   - Carries `fieldName` when the rule is field-specific (null when listing-wide)
   - Has a human-readable `reason` (string the deploy operator reads in error logs)
2. Verify the `parent::__construct()` call includes the full message with context (already done by `UnsupportedListingException` constructor from WP01).

**Files:** Integrated into T047.

### T049 — `BootValidationFailureTest` integration test

**Steps:**
1. Create `tests/Integration/Phase14/BootValidationFailureTest.php`:
   - Set up the test kernel
   - Register a `ServiceProvider` (test fixture) that implements `HasListingsInterface` and returns:
     - A listing with `pageSize: 2000` (Rule A violation; no `allowUnbounded()`)
   - Boot the kernel
   - Assert `UnsupportedListingException` thrown with:
     - `$exception->listingId === 'test_listing'`
     - `$exception->reason === 'pageSize exceeds 1000 without allowUnbounded()'`
   - Repeat for at least 3 other rule violations:
     - Unknown entity type (Rule D)
     - Unknown field (Rule F)
     - Langcode filter on non-translatable (Rule I)

**Files:** `tests/Integration/Phase14/BootValidationFailureTest.php` (new, ~150 lines).

**Note:** Phase 14 directory is correct per plan.md project structure. If a `tests/Integration/Phase13/` is current ceiling, check with `ls tests/Integration/` and bump if Phase 14 doesn't exist yet.

### T050 — Unit tests covering each rule

**Steps:**
1. Create `packages/listing/tests/Unit/ListingDefinitionValidatorTest.php`:
   - One test per rule (A through I) — positive (valid input passes) + negative (invalid input throws with expected reason)
   - Reuse a fixture `EntityTypeManager` with declared entity types (some translatable, some not, with various fields)
   - Validator construction is cheap — instantiate per test

**Files:** Test (~250 lines).

## Test strategy

- Unit tests cover each rule in isolation
- Integration test proves the kernel actually refuses to boot
- Validator is fast (~1ms per listing as estimated in plan.md complexity section) — no perf gate

## Definition of Done

- [ ] `ListingDefinitionValidator.php` exists with all 9 rule families implemented
- [ ] Every rule has positive + negative unit test
- [ ] `BootValidationFailureTest` exercises at least 4 distinct rule failures from a real kernel boot
- [ ] `composer cs-check` + `composer phpstan` green
- [ ] `vendor/bin/phpunit packages/listing/tests/Unit/ListingDefinitionValidatorTest.php` green
- [ ] `vendor/bin/phpunit tests/Integration/Phase14/BootValidationFailureTest.php` green

## Risks

| Risk | Mitigation |
|---|---|
| Rule H (operator-type compat) ambiguous on edge cases (e.g., is `LT` valid on a string field?) | Per ADR 010, comparisons on strings use lexicographic order — allow it. Document in validator class docblock |
| Rule F (field existence) requires reading the entity-type schema | Entity type has `getFields()` or equivalent; use the established M-001 API |
| Validator throws partway through registry iteration | Fail-fast is correct per FR-053 — first failure halts validation. Multi-failure reporting could be a follow-up |
| Test fixtures lack a translatable entity type | Reuse M-006's `TranslatableArticleFixture` (after WP01's PSR-4 placement); if it's hard to access from this test, create a local mini-fixture |

## Reviewer guidance

- Verify EVERY raise path carries `$listingId` — even Rule D ("entity type doesn't exist") still names the failing listing.
- Verify failure messages name the failing listing AND field (when applicable) so deploy ops have actionable context.
- Verify the integration test asserts the EXCEPTION TYPE, not just `Throwable` (must be `UnsupportedListingException`).
- Verify validator is fast enough for dev-loop hot reload (~1 ms per listing). If slower, profile + flag.

## Implementation command

```bash
spec-kitty agent action implement WP10 --agent <name>
```

## Activity Log

- 2026-05-16T21:01:33Z – claude:sonnet:python-implementer:implementer – shell_pid=41723 – Started implementation via action command
- 2026-05-16T21:13:03Z – claude:sonnet:python-implementer:implementer – shell_pid=41723 – WP10 ready: 9-rule ListingDefinitionValidator with fail-fast at boot. 24 unit tests (all rules positive+negative) and 5 Phase14 boot-validation integration tests pass. cs-check, phpstan, composer-policy, package-layers green. NOTE: PackageManifestCompiler::warm() does not exist yet; final wiring is WP11 scope. Integration test exercises the same provider->discoverer->registry->validator chain the compiler will use. DEVIATION: composer.json edit adds waaseyaa/field as required dep (layer-allowed L3->L1).
- 2026-05-16T21:13:45Z – claude:opus:python-reviewer:reviewer – shell_pid=45049 – Started review via action command
