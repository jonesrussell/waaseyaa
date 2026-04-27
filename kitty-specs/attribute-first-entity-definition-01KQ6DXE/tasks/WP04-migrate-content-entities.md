---
work_package_id: WP04
title: Migrate Production Entity Classes (Content Track)
dependencies:
- WP03
requirement_refs:
- FR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T017
- T018
- T019
- T020
- T021
- T022
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "28868"
history:
- date: '2026-04-27'
  note: Initial generation by /spec-kitty.tasks.
authoritative_surface: packages/genealogy/src
execution_mode: code_change
mission_id: 01KQ6DXEQ01S6PVPT6KF5946TA
mission_slug: attribute-first-entity-definition-01KQ6DXE
owned_files:
- packages/genealogy/src/Entity/**
- packages/genealogy/src/GenealogyServiceProvider.php
- packages/node/src/Node.php
- packages/node/src/NodeServiceProvider.php
- packages/note/src/Note.php
- packages/note/src/NoteServiceProvider.php
- packages/taxonomy/src/Term.php
- packages/taxonomy/src/Vocabulary.php
- packages/taxonomy/src/TaxonomyServiceProvider.php
- packages/user/src/User.php
- packages/user/src/UserServiceProvider.php
tags: []
---

# WP04 — Migrate Production Entity Classes (Content Track)

## Branch Strategy

- **Planning base**: `main`. **Merge target**: `main`. Worktree per lane.

## Objective

Migrate all content-track production entity classes (genealogy/*, node/Node, note/Note, taxonomy/Term + Vocabulary, user/User) and their ServiceProviders from the legacy `EntityType(fieldDefinitions: [...])` registration to attribute-first form using `#[Field]` and `EntityType::fromClass()`.

## Context

Each entity follows the same migration recipe. Read these once before starting:
- `kitty-specs/attribute-first-entity-definition-01KQ6DXE/quickstart.md` (the worked migration example)
- `kitty-specs/attribute-first-entity-definition-01KQ6DXE/contracts/php-api.md` (final API surface)
- `kitty-specs/attribute-first-entity-definition-01KQ6DXE/data-model.md` (mapping table for unfamiliar field types)
- `packages/note/src/NoteServiceProvider.php` (illustrative example of the current `EntityType(fieldDefinitions: …)` shape)

---

## Migration Recipe (apply per entity)

For each entity class + provider pair:

1. **Identify the current shape**: Open the package's `ServiceProvider.php`. Note the `new EntityType(...)` call: capture `id`, `label`, `description`, `class`, `keys`, `group`, `fieldDefinitions`, and any other params.
2. **Update the entity class** (`packages/<pkg>/src/<EntityClass>.php`):
   - Add `#[ContentEntityType(id: '…', label: '…', description: '…')]` with values from the provider.
   - Add or update `#[ContentEntityKeys(...)]` with the keys map from the provider.
   - For each entry in `fieldDefinitions`, ensure the entity class has a public typed property of matching name and type, decorated with `#[Field]` carrying the relevant overrides (label, description, required, defaultValue, type when not inferred).
   - Drop any boilerplate constructor override that just delegates to parent — attributes carry the entity-type id and keys now.
3. **Update the ServiceProvider**:
   - Replace `$this->entityType(new EntityType(... fieldDefinitions: [...] ...));` with:
     ```php
     $this->entityType(EntityType::fromClass(
         MyEntity::class,
         group: 'content',  // or whatever group the old call used
         // pass other overrides as needed
     ));
     ```
   - Drop the old field-definition array. If the old code had a "keep in sync with defaults/foo.yaml" comment, update it: the YAML defaults are still authoritative for content-as-data; the PHP class is now authoritative for type metadata.
4. **Verify**:
   - `vendor/bin/phpunit packages/<pkg>/` is green.
   - `vendor/bin/phpstan analyse packages/<pkg>/` clean.

---

## Subtask Guidance

### T017 — Migrate genealogy entity classes (4 files)

**Files**: `packages/genealogy/src/Entity/{GenealogyEvent,GenealogyFamily,GenealogyPerson,GenealogyTree}.php` + `packages/genealogy/src/GenealogyServiceProvider.php`.

**Steps**:
1. Open `packages/genealogy/src/GenealogyServiceProvider.php`. Each entity-type registration block becomes one entity-class migration.
2. For each entity class file:
   - Add `#[ContentEntityType]` and `#[ContentEntityKeys]` reading from the provider's old call.
   - Add typed properties decorated with `#[Field]` matching the field shape.
   - Drop the constructor override.
3. Update the provider's `register()` to use `EntityType::fromClass()` for each.
4. Run `vendor/bin/phpunit packages/genealogy/`.

**Validation**:
- [ ] All 4 entity classes have `#[ContentEntityType]` + `#[Field]` properties.
- [ ] `GenealogyServiceProvider` calls `EntityType::fromClass()` only.
- [ ] Genealogy package tests are green (modulo test fixtures, which migrate in WP07).

**Edge cases**: GenealogyTree may carry a `entity_reference` field to a root `GenealogyPerson`. Use `#[Field(type: 'entity_reference', settings: ['target_type' => 'genealogy_person'])]`.

---

### T018 — Migrate `packages/node/src/Node.php`

**Files**: `packages/node/src/Node.php`, `packages/node/src/NodeServiceProvider.php`.

**Steps**: Apply the migration recipe. Node likely has fields for title, body, status, type (bundle key). Ensure bundle support continues — `#[ContentEntityKeys(bundle: 'type', ...)]` should map the bundle to the existing storage column.

**Validation**:
- [ ] `vendor/bin/phpunit packages/node/` green.

---

### T019 — Migrate `packages/note/src/Note.php`

**Files**: `packages/note/src/Note.php`, `packages/note/src/NoteServiceProvider.php`.

**Steps**: Apply the recipe. The current `NoteServiceProvider` has clear field shapes (title: string required; body: text optional). Worked example for the migration.

**Validation**:
- [ ] `vendor/bin/phpunit packages/note/` green.

---

### T020 — Migrate `packages/taxonomy/src/{Term,Vocabulary}.php`

**Files**: `packages/taxonomy/src/Term.php`, `packages/taxonomy/src/Vocabulary.php`, `packages/taxonomy/src/TaxonomyServiceProvider.php`.

**Steps**: Two entity classes in one provider. Migrate both in this subtask. Term likely references Vocabulary via `entity_reference`. Use settings appropriately.

**Validation**:
- [ ] `vendor/bin/phpunit packages/taxonomy/` green.

---

### T021 — Migrate `packages/user/src/User.php`

**Files**: `packages/user/src/User.php`, `packages/user/src/UserServiceProvider.php`.

**Steps**: Apply the recipe. User entity carries auth-relevant fields; double-check any `readOnly` or password-hash fields are correctly annotated.

**Validation**:
- [ ] `vendor/bin/phpunit packages/user/` green.

**Risks**: User class may have additional methods beyond entity behavior (e.g., `hasRole()`). Don't accidentally remove them when dropping the constructor override.

---

### T022 — Update content-track ServiceProviders (rollup)

**Purpose**: Final pass to ensure all 5 ServiceProviders consistently use `EntityType::fromClass()`.

**Steps**:
1. Verify each of `GenealogyServiceProvider`, `NodeServiceProvider`, `NoteServiceProvider`, `TaxonomyServiceProvider`, `UserServiceProvider`:
   - No `new EntityType(...)` calls remain — all replaced with `EntityType::fromClass(...)`.
   - All necessary overrides (`group`, `storageClass`, etc.) are passed as named args.
2. Run `vendor/bin/phpunit packages/genealogy/ packages/node/ packages/note/ packages/taxonomy/ packages/user/` — all green.

**Validation**:
- [ ] `grep -rn 'new EntityType(' packages/{genealogy,node,note,taxonomy,user}/src/` returns 0.

---

## Definition of Done

- All 6 subtasks ticked.
- All 5 content-track packages have green tests.
- No file outside `owned_files` modified.
- `grep -rn 'fieldDefinitions:' packages/{genealogy,node,note,taxonomy,user}/src/` returns 0.

## Risks

- **YAML defaults drift**: `defaults/<pkg>/<entity>.yaml` may have field-shape data that mirrors the PHP fieldDefinitions array. The migration shouldn't change YAML content — those defaults are for content seeding, not type metadata. If a comment says "keep in sync", update the comment to clarify roles.
- **Bundle entity types**: Node uses bundles. The `#[ContentEntityKeys(bundle: 'type')]` plus `bundleEntityType: 'node_type'` in `EntityType::fromClass()` overrides must combine correctly.
- **entity_reference fields**: target type IDs in settings must match registered types; double-check `target_type` strings.

## Reviewer guidance

- For each entity class, verify the `#[Field]` declarations match the original `fieldDefinitions` array entry-for-entry.
- For each provider, verify no overrides were dropped silently.
- Diff the entity class against pre-migration: dropped lines should be only the boilerplate constructor; everything else should be additive (attributes + properties).

## Implementation command

```
spec-kitty agent action implement WP04 --agent <name>
```

## Activity Log

- 2026-04-27T04:16:40Z – claude:opus-4-7:implementer:implementer – shell_pid=28820 – Started implementation via action command
- 2026-04-27T04:26:29Z – claude:opus-4-7:implementer:implementer – shell_pid=28820 – Ready for review
- 2026-04-27T04:28:17Z – claude:opus-4-7:reviewer:reviewer – shell_pid=28868 – Started review via action command
- 2026-04-27T04:29:53Z – claude:opus-4-7:reviewer:reviewer – shell_pid=28868 – All 5 deviations defensible: timestamp/integer collapse is field-package gap; entity_reference workaround acceptable pending WP01 inferrer extension; config entities (NodeType, Vocabulary) use new EntityType per plan AD-3; UserBlock changes bounded to migration recipe; residual fieldDefinitions: are ContentEntityBase parent::__construct calls per spec C-006. Tests 375/375, PHPStan clean, only the 2 expected config-entity exceptions remain.
- 2026-04-27T06:13:12Z – claude:opus-4-7:reviewer:reviewer – shell_pid=28868 – Done override: Mission merged in ce123bfe
