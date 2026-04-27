---
work_package_id: WP03
title: Inferrer refactor
dependencies:
- WP02
requirement_refs:
- FR-009
- FR-010
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T012
- T013
- T014
- T015
agent: "claude:sonnet:reviewer:reviewer"
shell_pid: "5004"
history:
- timestamp: '2026-04-27T06:43:14Z'
  action: created
  by: /spec-kitty.tasks
authoritative_surface: packages/entity/src/Attribute/
execution_mode: code_change
owned_files:
- packages/entity/src/Attribute/FieldTypeInferrer.php
- packages/entity/src/Attribute/Field.php
- packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php
tags: []
---

# WP03 — Inferrer refactor

**Mission**: `field-type-enum-plugin-01KQ6SJG`
**Branch strategy**: planning + merge target = `main`. Worktree allocated by `lanes.json`. Base from WP02's resolved branch. Parallelizable with WP04 (different files).

## Objective

Refactor `FieldTypeInferrer` so that backed-enum-typed properties on attribute-first entities yield `'enum' + settings.enum_class` instead of the legacy `'string' + settings.enum_class` bridge. Update the `Field` attribute docstring example. Update the inferrer test suite. The legacy "explicit string type on a backed-enum property" affordance is removed (AS-8) — that combination becomes an error.

## Context

- Spec: [../spec.md](../spec.md) (FR-009, AS-6, AS-8, C-004)
- Research: [../research.md](../research.md) R3, R4
- Contract: [../contracts/inferrer-and-constraint-builder.md](../contracts/inferrer-and-constraint-builder.md)

Pre-refactor target lines: `packages/entity/src/Attribute/FieldTypeInferrer.php:144-148` (emission) and `:27-44` (`VALID_TYPE_IDS`). Pre-refactor test target lines: `packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php:78-85`, `:139-148`, `:150`.

## Owned files

- `packages/entity/src/Attribute/FieldTypeInferrer.php` — type-id whitelist + emission
- `packages/entity/src/Attribute/Field.php` — docstring example
- `packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php` — test assertions

Do **not** modify the constraint builder (WP04) or `entity-system.md` (WP05).

## Subtasks

### T012 [P] — Add `'enum'` to `VALID_TYPE_IDS`

**Purpose**: Make the inferrer's emission whitelist accept the new type id.

**Steps**:
1. Open `packages/entity/src/Attribute/FieldTypeInferrer.php`.
2. Locate the `VALID_TYPE_IDS` constant (lines 27–44 per research R4).
3. Add `'enum'` in alphabetical order with the other entries.
4. Search the entity package for other consumers of `VALID_TYPE_IDS`:
   ```
   grep -rn "VALID_TYPE_IDS" packages/entity/
   ```
   If any consumer maintains a parallel allowlist, update it in this WP and add to `owned_files` (declare the expansion in the WP review notes).

**Validation**:
- [ ] `'enum'` appears in `VALID_TYPE_IDS`.
- [ ] Grep finds no other allowlist requiring update (or the additional file is added to the WP and updated).

### T013 [P] — Flip the emission line

**Purpose**: Backed-enum-typed properties now yield `'enum'`.

**Steps**:
1. In `FieldTypeInferrer.php` around line 144–148, find the branch:
   ```php
   if (\class_exists($phpTypeName) && \is_subclass_of($phpTypeName, \BackedEnum::class)) {
       $settings['enum_class'] = $phpTypeName;
       return 'string';
   }
   ```
2. Replace `return 'string'` with `return 'enum'`.
3. The inferrer no longer chooses `string` vs `int` based on backing type — that responsibility moves to `EnumItem::schemaFor()`. Confirm there is no remaining branch in this file that picks a backing-type-specific id for backed enums.
4. If the inferrer also has a separate "explicit type override" branch that previously preserved `enum_class` when the user wrote `type: 'string'` on a backed-enum property, decide: per AS-8 / C-004 this combination is now invalid. Two options:
   - **Preferred**: raise an exception with a clear message (`"Field {field}: explicit type='string' on backed-enum property {prop} is no longer supported. Remove the explicit type or use type='enum' (enum_class is inferred automatically)."`).
   - **Acceptable fallback**: silently re-route `type='string' + BackedEnum` to `type='enum'`, but log a deprecation. This is weaker — prefer raising.
   Consult research R3's note on `FieldTypeInferrerTest.php:139-148` (the `explicit_string_type_on_backed_enum_keeps_inferred_enum_class` test). The replacement test in T015 should match the chosen behavior.

**Validation**:
- [ ] Direct `BackedEnum` property → `['type' => 'enum', 'settings' => ['enum_class' => FQCN]]`.
- [ ] Explicit `type='string'` on a `BackedEnum` property → error (or recovered, per the choice above; document the choice in the commit message).

### T014 [P] — Update `Field` attribute docstring

**Purpose**: Examples in the framework match the new canonical shape.

**Steps**:
1. Open `packages/entity/src/Attribute/Field.php`.
2. Find the docblock (likely a class-level docblock or example in the constructor docblock) that mentions `enum_class` in settings.
3. Replace the example with the canonical shape:
   ```
   #[Field]
   public Status $status;       // Inferred as type='enum', settings={enum_class: Status::class}
   ```
4. Remove any reference to the legacy `'string' + enum_class` shape.

**Validation**:
- [ ] No mention of `'string' + enum_class` remains in the file.
- [ ] Example reflects the new shape.

### T015 [P] — Update `FieldTypeInferrerTest.php`

**Purpose**: Tests assert the new shape and the AS-8 rejection.

**Steps**:
1. Open `packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php`.
2. Locate the data-provider rows around lines 78–85 — the `'BackedEnum → string + enum_class (required/optional)'` cases. Rename to `'BackedEnum → enum + enum_class (required/optional)'` and update the expected output to:
   ```php
   ['type' => 'enum', 'settings' => ['enum_class' => InferrerSampleEnum::class]]
   ```
   (Required vs optional: keep the existing required/optional distinction in the data provider — the inferrer's nullability handling is unchanged.)
3. Locate the test `explicit_string_type_on_backed_enum_keeps_inferred_enum_class` (lines 139–148, assertion at 150). Replace it with one of:
   - **If T013 raises**: a test `explicit_string_type_on_backed_enum_is_rejected` that asserts the inferrer throws when given an explicit `type='string'` annotation on a backed-enum property, with the exception message mentioning the property name.
   - **If T013 recovers**: a test `explicit_string_type_on_backed_enum_recovers_as_enum` that asserts the inferrer returns the canonical `'enum' + enum_class` shape and emits a deprecation log.
4. Run the inferrer test suite:
   ```bash
   ./vendor/bin/phpunit packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php
   ```
5. If the test fixture file `packages/entity/tests/Fixtures/AttributeFirstEntities/InferrerTestFixtures.php` (research R3) needs updating to add an int-backed enum sample, add the int-backed sample and the corresponding data-provider row in this WP. Add the fixture to `owned_files` and document the expansion in commit notes.

**Validation**:
- [ ] All inferrer tests pass.
- [ ] At least one int-backed and one string-backed sample exercise the new shape.
- [ ] The AS-8 behavior (reject or recover) has explicit test coverage.

## Definition of Done

- [ ] `VALID_TYPE_IDS` includes `'enum'`.
- [ ] Inferrer emits `'enum'` for any backed-enum-typed property.
- [ ] Explicit `type='string'` on a backed-enum property is no longer silently bridged (rejected or recovered with deprecation, documented in commit).
- [ ] `Field` attribute docstring shows the new canonical example.
- [ ] `./vendor/bin/phpunit packages/entity/tests/Unit/Attribute/` is green.
- [ ] No file outside `owned_files` is modified.

## Risks

| Risk | Mitigation |
|------|------------|
| Other allowlists in the entity package may also need `'enum'` added. | T012 includes a grep step. |
| Existing inferrer tests rely on shared fixtures that other tests also use; renaming a fixture enum could break unrelated tests. | Don't rename existing fixtures; add new ones if needed. |
| The AS-8 decision (reject vs. recover) may need product input. | Default to "reject with clear message" per the spec's hard-cutover stance; if the reviewer disagrees, they can ask for the soft path. |

## Reviewer guidance

- Diff in `FieldTypeInferrer.php` should be tiny — one constant entry, one return value, possibly one new exception throw.
- Diff in tests should be focused on the two locations called out above.
- Verify that no test still asserts the old `'string' + enum_class` shape.

## Implementation command

```bash
spec-kitty agent action implement WP03 --agent <name>
```

## Activity Log

- 2026-04-27T07:09:33Z – claude:sonnet:implementer:implementer – shell_pid=35584 – Started implementation via action command
- 2026-04-27T07:13:55Z – claude:sonnet:implementer:implementer – shell_pid=35584 – Ready for review
- 2026-04-27T07:14:36Z – claude:sonnet:reviewer:reviewer – shell_pid=5004 – Started review via action command
- 2026-04-27T07:16:27Z – claude:sonnet:reviewer:reviewer – shell_pid=5004 – Review passed: VALID_TYPE_IDS gains 'enum'; backed-enum properties emit type='enum'+enum_class with no string/int branching (column shape owned by EnumItem per WP02). AS-8 rejection fires with the exact diagnostic from the brief and is asserted in explicit_string_type_on_backed_enum_is_rejected (checks property name, type='enum' suggestion, and 'backed-enum' phrasing). Field docstring shows the new canonical shape. Inferrer test suite covers both int-backed and string-backed via new InferrerSampleIntEnum fixture. Scope clean: only the 3 owned Attribute/ files plus the in-scope fixture expansion (documented in commit body). Entity tests 337/741 green, field tests 314/479 green. Production-wiring deferral (EntityMetadataReader is static; threading FieldTypeManager would touch call sites outside Attribute/) is correctly out of WP03 surface. Carry-forward: bare FieldDefinition::toJsonSchema() on an enum field still returns ['type'=>'string'] via legacyJsonSchema default arm; no production caller hits this today, must be addressed when manager wiring lands.
