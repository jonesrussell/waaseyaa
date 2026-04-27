---
work_package_id: WP04
title: Constraint builder migration
dependencies:
- WP02
requirement_refs:
- FR-010
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T016
- T017
- T018
agent: "claude:sonnet:reviewer:reviewer"
shell_pid: "29072"
history:
- timestamp: '2026-04-27T06:43:14Z'
  action: created
  by: /spec-kitty.tasks
authoritative_surface: packages/entity/src/Validation/
execution_mode: code_change
owned_files:
- packages/entity/src/Validation/FieldDefinitionConstraintBuilder.php
- packages/entity/tests/Unit/Validation/FieldDefinitionConstraintBuilderTest.php
- packages/entity/tests/Unit/Validation/Fixtures/EnumValidationFixtures.php
tags: []
---

# WP04 — Constraint builder migration

**Mission**: `field-type-enum-plugin-01KQ6SJG`
**Branch strategy**: planning + merge target = `main`. Worktree allocated by `lanes.json`. Base from WP02's resolved branch. Parallelizable with WP03 (different files).

## Objective

Refactor `FieldDefinitionConstraintBuilder` so that enum validation is scoped to `type='enum'` fields and delegates case-value lookup to `EnumItem::casesForEnumClass()`. Remove the `enumClass` (camelCase) alias read and the legacy `'string' + enum_class` branch. After this WP, a `'string'` field that happens to carry an `enum_class` setting receives no `Choice` constraint on that basis (per AS-8).

## Context

- Spec: [../spec.md](../spec.md) (FR-005, AS-7, AS-8, C-003, C-004)
- Research: [../research.md](../research.md) R3
- Contract: [../contracts/inferrer-and-constraint-builder.md](../contracts/inferrer-and-constraint-builder.md)

Pre-refactor target lines: `packages/entity/src/Validation/FieldDefinitionConstraintBuilder.php:67-78`.

## Owned files

- `packages/entity/src/Validation/FieldDefinitionConstraintBuilder.php` — refactor
- `packages/entity/tests/Unit/Validation/FieldDefinitionConstraintBuilderTest.php` — update / add (filename to confirm in repo)
- `packages/entity/tests/Unit/Validation/Fixtures/EnumValidationFixtures.php` (new) — local enum fixtures if not already present

If the test file uses a different name in the actual repo, rename `owned_files` to match in the same commit.

## Subtasks

### T016 [P] — Scope enum logic to `type='enum'` and delegate to plugin

**Purpose**: Single, plugin-owned validation path for enum fields.

**Steps**:
1. Open `packages/entity/src/Validation/FieldDefinitionConstraintBuilder.php`.
2. Locate the existing enum branch (lines 67–78) — it currently reads `enum_class` (or `enumClass`) regardless of `$def->getType()` and constructs a `Choice` constraint via direct `BackedEnum::cases()`.
3. Replace with:
   ```php
   if ($def->getType() === 'enum') {
       $enumClass = $def->getSetting('enum_class');
       $values = array_keys(EnumItem::casesForEnumClass($enumClass));
       $constraints[] = new Choice(['choices' => $values]);
   }
   ```
4. Add `use Waaseyaa\Field\Item\EnumItem;` at the top of the file.
5. Verify that `casesForEnumClass()` raises a clear error if `enum_class` is missing/invalid (delegated from WP02). The constraint builder should NOT swallow that error — let it propagate; misconfigured fields should fail loudly at validator construction.

**Validation**:
- [ ] For a `type='enum'` field, the builder produces a `Choice` constraint with `choices` equal to the case backing values.
- [ ] For a `type='string'` field with `settings.enum_class` set, the builder produces NO `Choice` constraint on that basis (the legacy code path is gone).

### T017 [P] — Remove the legacy alias and bridge branch

**Purpose**: Eliminate the transitional bridge per C-004.

**Steps**:
1. Remove any read of `$def->getSetting('enumClass')` (camelCase). The canonical key per spec C-003 is `enum_class` (snake_case); aliases are not supported.
2. Remove the legacy `'allowed_values' / 'allowedValues'` branch IF and only IF it was added solely to support the enum bridge. (Inspect the surrounding context — `allowed_values` may be a separate, legitimate feature unrelated to backed enums; if so, leave it untouched.)
3. Search the file for any other reference to `enum_class` to confirm only the new `type='enum'` branch remains.

**Validation**:
- [ ] `grep -n "enumClass" packages/entity/src/Validation/FieldDefinitionConstraintBuilder.php` returns nothing.
- [ ] The file contains exactly one read of `enum_class`, inside the `type === 'enum'` branch.

### T018 [P] — Update / add validation tests

**Purpose**: Lock down the new behavior and the AS-8 rejection.

**Steps**:
1. Find the existing constraint-builder test (likely `FieldDefinitionConstraintBuilderTest.php` under `packages/entity/tests/Unit/Validation/`). If you find tests asserting the legacy `'string' + enum_class` produces a `Choice` constraint, replace them with assertions per the new behavior:
   - `'enum' + enum_class` → adds `Choice` with case backing values.
   - `'string' + enum_class` → adds NO `Choice` on that basis. (If `'string'` happens to carry separate `allowed_values`, that test is unrelated and should still pass.)
   - `'enum'` without `enum_class` → propagates `EnumFieldType.MissingEnumClass` (verify this surfaces from `casesForEnumClass`).
2. Add a fixture file `packages/entity/tests/Unit/Validation/Fixtures/EnumValidationFixtures.php` with at least one string-backed and one int-backed enum if no shared fixture is reusable.
3. Run:
   ```bash
   ./vendor/bin/phpunit packages/entity/tests/Unit/Validation/
   ```

**Validation**:
- [ ] All validation tests pass.
- [ ] At least one test exercises each of the three scenarios above.

## Definition of Done

- [ ] `FieldDefinitionConstraintBuilder` reads `enum_class` only inside the `type === 'enum'` branch, with no aliases.
- [ ] The legacy `'string' + enum_class` bridge code path is gone.
- [ ] `casesForEnumClass()` is the only path for case enumeration in this file.
- [ ] Tests cover `'enum'` happy path, `'string' + enum_class` no-op, and `'enum'` without `enum_class` error.
- [ ] `./vendor/bin/phpunit packages/entity/tests/Unit/Validation/` is green.
- [ ] No file outside `owned_files` is modified.

## Risks

| Risk | Mitigation |
|------|------------|
| `enumClass` (camelCase) alias is read elsewhere in the codebase. | Run a repo-wide grep; if hits exist outside the constraint builder, escalate — they're WP05's concern, not WP04's. |
| The legacy branch was load-bearing for some non-enum feature (e.g. some other validator that piggybacked on the same setting). | Read carefully before deleting; if a legitimate non-enum use exists, preserve it but scope the `enum_class` read to `type='enum'` only. |
| Removing the bridge causes a broader test-suite regression in higher-layer integration tests. | Run the full validation test directory; if a regression appears in a downstream test, that test is asserting the old bridge — update it as part of this WP and add the file to `owned_files`. |

## Reviewer guidance

- Diff should be small and focused: one branch replaced, one alias removed, one or two test cases rewritten.
- Confirm `EnumItem::casesForEnumClass` is being called — not a duplicate inline `BackedEnum::cases()` loop.
- Confirm error propagation: a missing `enum_class` should raise the plugin's exception, not be silently skipped.

## Implementation command

```bash
spec-kitty agent action implement WP04 --agent <name>
```

## Activity Log

- 2026-04-27T07:16:57Z – claude:sonnet:implementer:implementer – shell_pid=37536 – Started implementation via action command
- 2026-04-27T07:21:15Z – claude:sonnet:implementer:implementer – shell_pid=37536 – Ready for review
- 2026-04-27T07:21:46Z – claude:sonnet:reviewer:reviewer – shell_pid=29072 – Started review via action command
