---
work_package_id: WP14
title: Documentation + charter update + CHANGELOG + M-004 unblock
dependencies:
- WP01
- WP02
- WP03
- WP04
- WP05
- WP06
- WP07
- WP08
- WP09
- WP10
- WP11
- WP12
- WP13
requirement_refs:
- FR-062
- FR-063
- FR-064
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T073
- T074
- T075
- T076
- T077
- T078
- T079
history: []
authoritative_surface: docs/
execution_mode: code_change
owned_files:
- docs/cookbook/translating-an-entity-type.md
- docs/specs/stability-charter.md
- docs/specs/public-surface-map.md
- docs/public-surface-map.php
- docs/specs/entity-storage-translatable-revisions.md
- kitty-specs/entity-storage-translatable-revisions-01KRCDEE/spec.md
- CHANGELOG.md
- docs/specs/missions/README.md
- docs/specs/entity-storage-translations-v1.md
- kitty-specs/entity-storage-translations-v1-01KRF0FQ/spec.md
tags: []
agent: "claude:opus:waaseyaa-reviewer:reviewer"
shell_pid: "622666"
---

# WP14 — Documentation + charter update + CHANGELOG + M-004 unblock (mission close)

## Objective

Mission close: ship the cookbook recipe, update the stability charter §5.3, update the public surface map, remove the single-axis-translation BLOCKED bullet from M-004 (ADR-015 listing-pipeline bullet remains), CHANGELOG entry, mark M-006 shipped in the missions registry, and reconcile spec FR names with shipped reality.

## Context

- **Spec:** [`../spec.md`](../spec.md) §3.13 (FR-062..FR-064), §12 (SC-05)
- **Research:** [`../research.md`](../research.md) R1 (naming reconciliation note)
- **Quickstart:** [`../quickstart.md`](../quickstart.md) (source for cookbook recipe)

## Subtasks

### T073 — Author `docs/cookbook/translating-an-entity-type.md`

**Steps:**

1. Take the quickstart.md content as the basis. Reshape into a user-facing cookbook recipe:
   - Title: "Adding translations to an entity type"
   - Intro: when to use translatable entities, when NOT to (config entities, low-cardinality lookup tables).
   - 8 steps from quickstart.md.
   - "When things go wrong" section: each failure mode (missing default_langcode, removing default translation, fallback exhaustion).
   - Cross-references to ADR 017 and the migration generator contract.

**Files:** `docs/cookbook/translating-an-entity-type.md` (new, ~250 lines).

### T074 — Update `docs/specs/stability-charter.md` §5.3

**Steps:**

1. Find §5.3 "Stable surface" table. Add rows for:
   - `Waaseyaa\Entity\TranslatableInterface`
   - `Waaseyaa\Entity\Exception\EntityTranslationException`
   - `Waaseyaa\Entity\EntityType::__construct(...translatable: bool...)`
   - `Waaseyaa\Field\FieldDefinition::translatable(bool): self` (and `isTranslatable(): bool`)
   - `Waaseyaa\EntityStorage\SaveContext::withLangcode(string): self`
   - `Waaseyaa\EntityStorage\EntityRepository::findTranslations(EntityInterface): array`
   - Entity key string `'default_langcode'`
   - Config keys `translation.fallback_chain`, `translation.read_active_language`
   - 6 event-name constants (`PRE/POST_TRANSLATION_INSERT/UPDATE/DELETE`)
   - Access policy operation literal `'translate'`

2. If §3.2 has a beta-criterion table, mark criterion 9 (per-field translation surface) as satisfied with a reference to M-006.

**Files:** `docs/specs/stability-charter.md` (modify, ~30 lines added).

### T075 — Update `docs/specs/public-surface-map.md` and `docs/public-surface-map.php`

**Steps:**

1. Locate the public surface map files (both `.md` and `.php` siblings).
2. Add entries for each new stable-surface symbol per T074 list.
3. Mark `language()` on `TranslatableInterface` as deprecated.

**Files:** ~50 lines total.

### T076 — Remove M-004 single-axis-translation BLOCKED bullet

**Steps:**

1. Open `docs/specs/entity-storage-translatable-revisions.md`. The current banner says:
   > Two hard prerequisites missing: (1) single-axis translation substrate — **spec filed 2026-05-12 as [M-006 ...]** — must SHIP before this composition plans...; (2) ADR 015 listing pipeline...
2. Modify the banner: change "Two hard prerequisites" to "One hard prerequisite", remove the (1) sub-bullet (cite M-006's squash-merge commit), keep (2). Result:
   > **🛑 BLOCKED — DO NOT PLAN (2026-05-XX)** One hard prerequisite missing: ADR 015 listing pipeline... The single-axis translation substrate prerequisite was satisfied by **M-006 (`entity-storage-translations-v1`, squash <SHA>)**.

3. Do the same to `kitty-specs/entity-storage-translatable-revisions-01KRCDEE/spec.md`.

**Files:** ~10 lines net change.

### T077 — CHANGELOG entry

**Steps:**

1. Open `CHANGELOG.md`. Under `[Unreleased]`, add:
   ```markdown
   ### Added
   - **M-006 entity-storage-translations-v1:** Single-axis translation substrate. New `TranslatableInterface` surface (`getTranslation`, `addTranslation`, `removeTranslation`, `translations`, `fieldLangcode`, plus `defaultLangcode`/`activeLangcode`); per-field `FieldDefinition::translatable()` flag; `EntityType::translatable: true` now load-bearing; `sql-blob` and `sql-column` storage shapes for translatable types; configurable language fallback chain; lifecycle events with langcode (`PRE/POST_TRANSLATION_INSERT/UPDATE/DELETE`); `translate` access-policy operation; `bin/waaseyaa make:migration --add-translations`; framework-internal `test_translatable_entity` fixture. Unblocks one of two M-004 prerequisites. Governing ADR 017. Beta-gate per charter §3.2.9 cleared. Spec: docs/specs/entity-storage-translations-v1.md.
   ```
2. Append cross-refs at the end of the section.

**Files:** `CHANGELOG.md` (modify, ~10 lines).

### T078 — Mark M-006 shipped in missions/README.md

**Steps:**

1. Open `docs/specs/missions/README.md`. The M-006 row currently reads:
   ```
   | M-006 | Entity Storage — Single-Axis Translations v1 | `docs/specs/entity-storage-translations-v1.md` | ready (BETA-GATE; unblocks M-004 single-axis-translation prereq) | `mission.json` |
   ```
2. Update to:
   ```
   | M-006 | Entity Storage — Single-Axis Translations v1 | `docs/specs/entity-storage-translations-v1.md` | **shipped 2026-05-XX** (squash `<SHA>`) | `mission.json` |
   ```
3. Update the cross-mission dependency graph: M-006 is now shipped, M-004 is one prereq closer.
4. Update the header date.

**Files:** ~10 lines.

### T079 — Spec FR-name reconciliation

**Steps:**

1. Open `docs/specs/entity-storage-translations-v1.md` AND `kitty-specs/entity-storage-translations-v1-01KRF0FQ/spec.md`.
2. Add a §"Mission-close reconciliation" section (or insert into §14 "Open questions"):
   ```markdown
   ## Mission-close reconciliation (2026-05-XX)

   The spec FRs originally named `TranslatableEntityInterface`; shipped reality is `TranslatableInterface` (the existing minimal stub was expanded rather than replaced). Affected FRs: FR-006, FR-010, FR-014. Read those FRs with `TranslatableEntityInterface` → `TranslatableInterface` mentally substituted.

   The spec originally listed `final class EntityEvent`; shipped reality removed `final` so `TranslationEvent` can `extend EntityEvent`. Documented and intentional (minor public-surface change, no consumer breakage at framework level).
   ```
3. Also note the `language()` deprecated-alias addition (not in original spec but per research R1).

**Files:** ~30 lines added across 2 files.

## Definition of Done

- [ ] Cookbook recipe authored (FR-062).
- [ ] Charter §5.3 updated (FR-063); §3.2.9 marked satisfied.
- [ ] Public surface map updated (.md + .php siblings).
- [ ] M-004 BLOCKED banner amended (1 prereq remaining, not 2).
- [ ] CHANGELOG `[Unreleased]` bullet added.
- [ ] M-006 marked shipped in missions/README.md.
- [ ] Spec reconciliation note added to both spec files.
- [ ] `composer phpstan`, `composer cs-check`, `bin/check-package-layers` green.
- [ ] `tools/drift-detector.sh` reports no untouched affected specs.

## Risks

| Risk | Mitigation |
|---|---|
| Squash-merge SHA isn't known until after the PR squash. | Author docs with `<SHA>` placeholders; replace via a `git filter-branch`-equivalent simple sed in a post-merge fixup commit. Or, more practically: land WP14 in the same PR; reviewer fills in the SHA post-merge in a follow-up `chore(docs): stamp M-006 squash SHA` commit. |
| Public surface map may live in a different shape than expected. | Read the existing file first; mirror its structure precisely. |

## Reviewer guidance

- Verify M-004 banner correctly shows ONLY ADR-015 listing pipeline as remaining prereq.
- Verify cookbook recipe is consumer-facing (no spec/mission jargon — just "how do I translate an entity").
- Verify reconciliation note is honest about every spec-vs-shipped delta.
- Verify CHANGELOG bullet references the new stable surface comprehensively.

## Implementation command

```bash
spec-kitty agent action implement WP14 --agent <name>
```

## Activity Log

- 2026-05-13T00:49:04Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=618113 – Started implementation via action command
- 2026-05-13T01:03:26Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=618113 – Mission close: cookbook recipe authored; charter §5.3 + §3.2.9 updated; public-surface-map sweep (.md + .php); M-004 BLOCKED banner reduced from 2 prereqs to 1 (ADR-015 listing-pipeline remains); CHANGELOG [Unreleased] M-006 bullet added; M-006 marked shipped in missions/README; reconciliation note added to docs/specs/entity-storage-translations-v1.md §13a documenting deltas (TranslatableInterface kept, EntityEvent non-final, language() deprecated alias, ContextAwareAccessPolicyInterface companion, EntityRepository optional ctor params, default_langcode plumbing through ContentEntityKeys + EntityMetadataReader, findTranslations on EntityRepositoryInterface + EntityStorageDriverInterface). Carryover: full suite 7851/0 (was 46 errors); fixed via attribute keys on TranslatableTestEntity + ReadOnlyTranslatableTestEntity, default_langcode injection in SchemaPresenterTest helper, SchemaControllerTest + EntityTypeListHandlerTest fixture keys, and BC-preserving fallback in TranslatableEntityTrait::language() for non-translatable entities. Gates green: phpstan, cs-check, check-package-layers.
- 2026-05-13T01:03:57Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=622666 – Started review via action command
- 2026-05-13T01:06:48Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=622666 – WP14 approved: mission close — cookbook (241 lines, FR-jargon-free) + charter §5.3 stable surface (TranslatableInterface, EntityTranslationException, FieldDefinition::translatable, SaveContext::withLangcode, EntityRepository::findTranslations, ContextAwareAccessPolicyInterface, TranslationEvent + 6 event constants, default_langcode key, translation.* config keys, 'translate' op, EntityEvent non-final) + §3.2 criterion 9 SATISFIED with M-006 ref + public surface map sweep (.md + .php) + M-004 BLOCKED banner in canonical doctrine reduced to 1 hard prereq (ADR-015 listing pipeline; single-axis bullet removed) + CHANGELOG [Unreleased] M-006 bullet comprehensive + missions/README M-006 shipped-stamp + §13a reconciliation note documenting TranslatableEntityInterface→TranslatableInterface and EntityEvent non-final deltas. **Carryover claim VERIFIED: full suite 7851 tests / 0 errors / 0 failures (was 46 errors)** via attribute keys on TranslatableTestEntity + ReadOnlyTranslatableTestEntity, default_langcode injection in SchemaPresenterTest, fixture keys in SchemaControllerTest/EntityTypeListHandlerTest, and BC fallback in TranslatableEntityTrait::language() (scoped to that deprecated method only — assertTranslatable/defaultLangcode/getTranslation/addTranslation still throw correctly). Gates green: phpstan, cs-check, check-package-layers. Note: kitty-specs mirror of M-004 banner reverted by lane-branch-guard (commit ebb0b76a3) — canonical doctrine spec is the durable home per its own header marker; this is the implementer's documented carve-out and is acceptable. Mission ready for spec-kitty merge.
