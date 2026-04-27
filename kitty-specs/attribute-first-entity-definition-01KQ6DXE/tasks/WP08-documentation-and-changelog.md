---
work_package_id: WP08
title: Documentation, CHANGELOG, UPGRADING
dependencies:
- WP04
- WP05
requirement_refs:
- FR-006
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T040
- T041
- T042
- T043
agent: "claude:opus-4-7:implementer:implementer"
shell_pid: "6804"
history:
- date: '2026-04-27'
  note: Initial generation by /spec-kitty.tasks.
authoritative_surface: docs/specs/entity-system.md
execution_mode: code_change
mission_id: 01KQ6DXEQ01S6PVPT6KF5946TA
mission_slug: attribute-first-entity-definition-01KQ6DXE
owned_files:
- docs/specs/entity-system.md
- CHANGELOG.md
- UPGRADING.md
- kitty-specs/attribute-first-entity-definition-01KQ6DXE/meta.json
- kitty-specs/attribute-entity-classmap-discovery-01KQ6E2B/meta.json
- kitty-specs/bundle-scoped-field-attribute-01KQ6E2D/meta.json
- kitty-specs/entity-base-constructor-cleanup-01KQ6E2F/meta.json
- kitty-specs/entity-vector-indexed-attribute-01KQ6E2J/meta.json
tags: []
---

# WP08 — Documentation, CHANGELOG, UPGRADING

## Branch Strategy

- **Planning base**: `main`. **Merge target**: `main`.

## Objective

Update authoritative documentation to reflect the new attribute-first flow. Mark M1 as complete in mission metadata. Lift M2-M5 stubs from `status: stub` to `status: ready` so they can be planned next.

---

## Subtask Guidance

### T040 — Update `docs/specs/entity-system.md`

**Purpose**: The framework's authoritative entity-system reference must show the new pattern.

**Steps**:
1. Open `docs/specs/entity-system.md`. The document is large (1438 lines); be **surgical** — only change sections that describe the changed behavior.
2. **§EntityType Definition** (around line 456):
   - Replace the `new EntityType(... fieldDefinitions: [...] ...)` example with the attribute-first equivalent.
   - Use the `Note` example from `kitty-specs/attribute-first-entity-definition-01KQ6DXE/quickstart.md` as the canonical sample.
   - Mention `EntityType::fromClass()` as the canonical entry point for content entity types.
3. **§Public Surface** (around line 39):
   - Add `EntityType::fromClass(class)` to the public surface table.
   - Add `Waaseyaa\Entity\Attribute\Field` and the extended `Waaseyaa\Entity\Attribute\ContentEntityType` parameters.
4. **§EntityTypeAttribute (future plugin discovery)** (around line 490):
   - Update to reflect that classmap-based discovery is the M2 mission (`attribute-entity-classmap-discovery`) and link to that mission's stub.
5. Search for any other `fieldDefinitions:` mentions in the doc and replace with attribute references.
6. Search for any explicit `EntityTypeManager::assertClassMetadataMatchesEntityType` mentions; remove (the validator no longer exists).

**Files**:
- `docs/specs/entity-system.md` (modified — bounded changes; do not rewrite unrelated sections).

**Validation**:
- [ ] Spec describes the new flow accurately.
- [ ] No mentions of `fieldDefinitions:` constructor parameter.
- [ ] No mentions of the deleted validator.
- [ ] `quickstart.md`-style example is included.

---

### T041 — Update `CHANGELOG.md` with breaking-change entry

**Purpose**: Communicate the API break to consumers.

**Steps**:
1. Open `CHANGELOG.md`. Add an entry under the next unreleased version (or current alpha):
   ```markdown
   ## [Unreleased] — Attribute-first entity definition (M1)

   ### Breaking changes

   - `Waaseyaa\Entity\EntityType` constructor no longer accepts a `fieldDefinitions:` parameter. Field definitions must come from `#[Field]`-decorated entity properties via `EntityType::fromClass(MyEntity::class)`.
   - `Waaseyaa\Entity\EntityTypeManager::assertClassMetadataMatchesEntityType()` removed. With a single source of truth, the validator has no purpose.

   ### Added

   - `Waaseyaa\Entity\Attribute\Field` — declare entity fields directly on typed properties.
   - `Waaseyaa\Entity\EntityType::fromClass(string $class, ...$overrides): self` — static factory that builds an `EntityType` from a class's attribute metadata.
   - `Waaseyaa\Entity\Attribute\ContentEntityType` extended with `label` and `description` parameters.
   - `Waaseyaa\Entity\Tests\Helper\TestEntityType::stub()` — test-only helper for raw-shape `EntityType` construction.

   ### Migration

   See `UPGRADING.md` and `docs/specs/entity-system.md`.
   ```
2. If the project uses Keep-a-Changelog format, follow that.

**Files**:
- `CHANGELOG.md` (extended).

---

### T042 — Update `UPGRADING.md` with migration recipe

**Purpose**: Ensure any downstream apps following alpha releases have a clear migration path.

**Steps**:
1. Open `UPGRADING.md`. Add a section:
   ```markdown
   ## Upgrading to alpha.[X] — Attribute-first entity definition

   The `EntityType` constructor no longer accepts `fieldDefinitions:`. To migrate:

   **Before:**
   \`\`\`php
   $this->entityType(new EntityType(
       id: 'note',
       class: Note::class,
       keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title'],
       fieldDefinitions: [
           'title' => ['type' => 'string', 'required' => true],
           'body' => ['type' => 'text'],
       ],
   ));
   \`\`\`

   **After:**
   \`\`\`php
   // In Note.php:
   #[ContentEntityType(id: 'note', label: 'Note')]
   #[ContentEntityKeys(label: 'title')]
   final class Note extends ContentEntityBase {
       #[Field] public string $title;
       #[Field(type: 'text')] public ?string $body;
   }

   // In NoteServiceProvider.php:
   $this->entityType(EntityType::fromClass(Note::class));
   \`\`\`

   See `quickstart.md` in the M1 mission for the full inference table and override patterns.
   ```
2. If the project uses semver-tagged upgrade notes, place under the appropriate version section.

**Files**:
- `UPGRADING.md` (extended).

---

### T043 — Update mission status meta + stub follow-on missions

**Purpose**: Mark M1 as merged in metadata so the dashboard reflects reality; lift stub follow-on missions to `ready` so they can be planned next.

**Steps**:
1. Open `kitty-specs/attribute-first-entity-definition-01KQ6DXE/meta.json`. The `status` field will be set automatically by `spec-kitty agent mission accept` / `merge`. **Do NOT edit `mission_id`, `mission_slug`, `created_at`, `target_branch`.** This subtask doesn't actually modify M1's meta — it only updates the FOUR follow-on missions' meta files.
2. For each of the four follow-on stub missions, open their `meta.json`:
   - `kitty-specs/attribute-entity-classmap-discovery-01KQ6E2B/meta.json`
   - `kitty-specs/bundle-scoped-field-attribute-01KQ6E2D/meta.json`
   - `kitty-specs/entity-base-constructor-cleanup-01KQ6E2F/meta.json`
   - `kitty-specs/entity-vector-indexed-attribute-01KQ6E2J/meta.json`
3. Change `status: "stub"` to `status: "ready"`.
4. Optionally add a `predecessor_merged: true` field if the JSON schema accepts unknown keys (it does — meta.json is permissive).
5. Do **not** change `doctrine-dbal-cutover-01KQ6E2M`'s status — that mission has its own dependencies (Track 4 schema evolution) and isn't unblocked by M1 alone.

**Files**:
- 4 meta.json files (modified, single-key changes).

**Validation**:
- [ ] `spec-kitty status --mission attribute-entity-classmap-discovery-01KQ6E2B` shows `ready` (or whatever the dashboard surfaces).
- [ ] `doctrine-dbal-cutover-01KQ6E2M` still shows `stub`.

---

## Definition of Done

- All 4 subtasks ticked.
- `docs/specs/entity-system.md` reflects the new flow.
- CHANGELOG and UPGRADING entries committed.
- Follow-on missions visible as ready in the dashboard.

## Risks

- `entity-system.md` is large; accidentally rewriting unrelated sections is the main risk. Keep the diff bounded.

## Reviewer guidance

- Read the diff against `docs/specs/entity-system.md` carefully — only intended sections should change.
- Verify CHANGELOG entry placement matches project convention.
- Verify UPGRADING recipe matches the actual quickstart example.

## Implementation command

```
spec-kitty agent action implement WP08 --agent <name>
```

## Activity Log

- 2026-04-27T05:42:56Z – claude:opus-4-7:implementer:implementer – shell_pid=6804 – Started implementation via action command
- 2026-04-27T05:48:38Z – claude:opus-4-7:implementer:implementer – shell_pid=6804 – Ready for review: docs updated for attribute-first flow, CHANGELOG/UPGRADING entries added, 4 follow-on missions lifted to ready, 5 transitional gaps documented in entity-system.md
