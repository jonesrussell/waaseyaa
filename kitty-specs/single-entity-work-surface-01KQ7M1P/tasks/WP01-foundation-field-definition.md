---
work_package_id: WP01
title: 'Foundation: enrich FieldDefinition'
dependencies: []
requirement_refs:
- FR-003
- FR-004
- NFR-005
- NFR-006
- NFR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-single-entity-work-surface-01KQ7M1P
base_commit: c450d9bb6190235584770f85a4e288244157c89b
created_at: '2026-04-27T15:53:21.069238+00:00'
subtasks:
- T001
- T002
- T003
- T004
shell_pid: "12988"
agent: "claude:sonnet-4-6:implementer:implementer"
history:
- date: '2026-04-27'
  note: Generated from plan.md + research.md + data-model.md.
authoritative_surface: packages/field/
execution_mode: code_change
mission_id: 01KQ7M1PHWD8QAQPJC91RAVE0T
mission_slug: single-entity-work-surface-01KQ7M1P
owned_files:
- packages/field/src/FieldDefinition.php
- packages/field/src/FieldDefinitionInterface.php
- packages/field/tests/Unit/FieldDefinitionTest.php
- UPGRADING.md
tags: []
---

# WP01 — Foundation: enrich `FieldDefinition`

## Objective

Add two optional properties to the existing `Waaseyaa\Field\FieldDefinition` value object — `group: string` and `promptAliases: list<string>` — to support F2's bundle-template registry and F5's prompt-matching importer. Add corresponding getters to `FieldDefinitionInterface`. Author the UPGRADING.md migration recipe documenting the breaking constructor change.

This WP is the foundation for the entire mission. WP02, WP03, WP07, and WP09 all depend on the enriched `FieldDefinition` API.

## Context (read first)

- **spec.md** — requirements FR-003, FR-004 introduce field templates with `group` and `prompt_aliases`. The "single canonical registry" decision (planning Q1: A) means we extend `FieldDefinition` rather than creating a parallel registry.
- **research.md** Q1 — settles the constructor signature: append `string $group = '', array $promptAliases = []` at the end. Defaults keep existing call sites compiling at the source level; **named-argument call sites continue to work unchanged**. Positional call sites that pass the trailing parameters (rare but possible) will need updating — this is acceptable per DIR-003 (greenfield removal policy, mission #5 merged).
- **data-model.md § 2** — formal value-object shape post-mission.
- **DIR-003** — breaking changes are explicit, announced via UPGRADING.md, and not softened with `@deprecated` wrappers. Do **not** introduce a `FieldDefinitionLegacy`, do **not** add overload helpers, do **not** preserve a "without group/aliases" constructor variant.

## Branch Strategy

- **Planning base**: `main`
- **Final merge target**: `main`
- Execution worktree path and branch are computed by `spec-kitty agent mission finalize-tasks` and recorded in `lanes.json`. Use `spec-kitty agent action implement WP01 --agent <name>` to enter the correct workspace.

## Subtasks

### T001 — Add `group` and `promptAliases` constructor parameters

**File**: `packages/field/src/FieldDefinition.php`

**Steps**:

1. Append the two new parameters at the end of the constructor (after `?FieldTypeManager $fieldTypeManager = null`):

   ```php
   private string $group = '',
   private array $promptAliases = [],
   ```

2. Both are private (matching the existing readonly constructor-promoted property pattern). The class is `final readonly`, so the properties become readonly automatically.

**Validation**:
- Constructing `new FieldDefinition(name: 'x', type: 'string')` still works (defaults preserved).
- Constructing `new FieldDefinition(name: 'x', type: 'string', group: 'about', promptAliases: ['x', 'X'])` works.
- PHPStan level 5 reports no errors for the file.

### T002 — Add getters to interface and class

**Files**:
- `packages/field/src/FieldDefinitionInterface.php`
- `packages/field/src/FieldDefinition.php`

**Steps**:

1. In `FieldDefinitionInterface`, add (place after `getLabel(): string` for grouping):

   ```php
   /**
    * The optional group key used by the form descriptor builder for grouping fields visually.
    * Empty string means "no group".
    */
   public function getGroup(): string;

   /**
    * Optional prompt aliases used by the structured-import pipeline for fuzzy-tolerant matching.
    * Empty list means "match by field name only".
    *
    * @return list<string>
    */
   public function getPromptAliases(): array;
   ```

2. In `FieldDefinition`, implement:

   ```php
   public function getGroup(): string
   {
       return $this->group;
   }

   /** @return list<string> */
   public function getPromptAliases(): array
   {
       return $this->promptAliases;
   }
   ```

**Validation**:
- `FieldDefinitionInterface` declares both methods.
- `FieldDefinition` implements both, returning constructor-supplied values.
- PHPStan level 5 reports no errors; the `list<string>` generic is honored.

### T003 — Update `FieldDefinitionTest`

**File**: `packages/field/tests/Unit/FieldDefinitionTest.php`

**Steps**:

1. Add tests covering:
   - `getGroup()` returns the constructor-supplied group.
   - `getGroup()` returns `''` (empty string) by default.
   - `getPromptAliases()` returns the constructor-supplied list.
   - `getPromptAliases()` returns `[]` by default.
   - `FieldDefinitionInterface` enforces both methods (PHPUnit can verify by typing the test variable as the interface).

2. Use the existing test class style (`#[Test]`, `#[CoversClass(FieldDefinition::class)]`).

**Validation**:
- `./vendor/bin/phpunit packages/field/tests/Unit/FieldDefinitionTest.php` passes.
- New tests cover both default and explicit-value paths for the two new properties.

### T004 — UPGRADING.md migration recipe

**File**: `UPGRADING.md` (project root; create if missing)

**Steps**:

1. Add an `## Unreleased` section at the top (or extend existing section if the file already has one).

2. Under it, add:

   ```markdown
   ### `FieldDefinition` constructor parameters added (Waaseyaa\Field)

   `Waaseyaa\Field\FieldDefinition::__construct` gained two trailing optional
   parameters: `string $group = ''` and `array $promptAliases = []`.

   - **Named-argument call sites** (recommended idiom) continue to work unchanged.
     No action required.
   - **Positional call sites that pass `$fieldTypeManager` as the last argument**
     continue to work unchanged.
   - **Positional call sites that pass arguments after `$fieldTypeManager`** —
     none should exist before this release because no such positional slots
     existed. If you have such call sites, switch to named arguments:

     ```php
     // Before:
     new FieldDefinition('title', 'string', 1, [], '', null, false, false, null, 'Title');

     // After (recommended — works regardless of constructor evolution):
     new FieldDefinition(
         name: 'title',
         type: 'string',
         label: 'Title',
     );
     ```

   `getGroup(): string` and `getPromptAliases(): array` were added to
   `FieldDefinitionInterface`. Custom implementations of the interface must
   implement these two methods.

   Reason: support for bundle-keyed field templates (mission
   `single-entity-work-surface-01KQ7M1P`) requires per-field grouping and
   alias metadata as first-class properties of the field definition. Per
   `DIR-003`, no compatibility shim is provided — implementers update in
   the same release.
   ```

3. Do not add a CHANGELOG.md entry in this WP. CHANGELOG entries for the entire mission are aggregated in WP10 to avoid merge conflicts across parallel WPs.

**Validation**:
- `UPGRADING.md` exists with the section above.
- The recipe is concrete enough that an implementer of `FieldDefinitionInterface` can mechanically update their class.

## Definition of Done

- [ ] `FieldDefinition` constructor accepts `group` and `promptAliases` with documented defaults.
- [ ] `FieldDefinitionInterface` declares `getGroup()` and `getPromptAliases()`.
- [ ] `FieldDefinition` implements both getters.
- [ ] `FieldDefinitionTest` covers default and explicit-value paths for both new properties.
- [ ] `UPGRADING.md` has the migration recipe with the named-argument example.
- [ ] `./vendor/bin/phpunit packages/field/tests/Unit/FieldDefinitionTest.php` passes.
- [ ] `composer phpstan` (level 5) passes for `packages/field/`.
- [ ] `composer cs-check` passes.
- [ ] No code changes outside `packages/field/src/FieldDefinition.php`, `packages/field/src/FieldDefinitionInterface.php`, `packages/field/tests/Unit/FieldDefinitionTest.php`, `UPGRADING.md`.

## Risks

| Risk | Mitigation |
|---|---|
| Constructor-call cascade breaks downstream packages that pass positional args | Default values keep all existing positional calls compiling. Cascade only hits sites that *don't yet exist* (no one is passing `null` as an explicit `$fieldTypeManager` followed by positional `$group`). |
| Interface change breaks external `FieldDefinitionInterface` implementers | UPGRADING.md spells out the two new methods. Per DIR-003, no compatibility shim. |
| PHPStan level 5 complains about `list<string>` vs `array<string>` typing | Use `@param list<string>` / `@return list<string>` PHPDoc on both interface and class — PHPStan respects these. |

## Reviewer guidance

- Verify the constructor parameter list is in the order specified (research.md Q1) — `$group` before `$promptAliases`. The order is the contract for any positional call sites added in subsequent WPs.
- Verify both new properties are private (matching existing constructor-promoted style) and that `final readonly class` makes them readonly without explicit `readonly` keywords.
- Verify `UPGRADING.md` is concrete — a reader who has only this file should be able to update their codebase.
- This WP introduces a public-API breaking change. Per DIR-001's "no silent breaking changes" rule, the UPGRADING entry is **mandatory** — reject the WP if it is missing or vague.
- This WP intentionally does **not** edit CHANGELOG.md (deferred to WP10 to centralize the mission's CHANGELOG entry). Confirm CHANGELOG was not edited.

## Implementation command

```bash
spec-kitty agent action implement WP01 --agent <agent-name> --mission single-entity-work-surface-01KQ7M1P
```

No dependencies — can start immediately from `main`.

## Activity Log

- 2026-04-27T15:53:23Z – claude:sonnet-4-6:implementer:implementer – shell_pid=12988 – Assigned agent via action command
