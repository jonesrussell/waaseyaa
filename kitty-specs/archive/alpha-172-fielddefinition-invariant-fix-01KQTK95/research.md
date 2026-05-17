# Research: Alpha.172 FieldDefinition Invariant Fix

**Mission**: `01KQTK95TKYCKCFA1C07XE0CFM`
**Date**: 2026-05-04

---

## R0. Why this research exists

The spec says "the alpha.171 binding invariant rejects `FieldDefinition`s missing `targetEntityTypeId`." Before patching, the planner had to confirm:

1. The exact constructor shape of `FieldDefinition` and whether `targetEntityTypeId` is required, optional, or defaulted.
2. Where the bind-time invariant is enforced and what exception/message it throws.
3. Every framework call site that constructs a `FieldDefinition` and binds it to an entity type, with a clear pass/fail flag for each.
4. The canonical pattern used by clean call sites (so the fix is minimum-diff).
5. The CHANGELOG location and format for the alpha.172 release notes entry.
6. The current state of `docs/specs/entity-system.md` (so we know whether to touch it).

All findings come from a structured sweep of `packages/*/src/**` and `docs/specs/`.

## R1. `FieldDefinition` constructor

**File**: `packages/field/src/FieldDefinition.php` (line 15)

**Decision**: `targetEntityTypeId: string = ''` is constructor-optional, defaulted to empty string.

**Rationale**:
- Empty default exists for legacy / test-fixture call sites that never bind.
- Bind-time validation at the registry catches the difference between "constructed for inspection" and "constructed for registration".

**Alternatives considered**:
- *Make `targetEntityTypeId` required at construction*: a stronger guarantee, but a much wider blast radius — every test fixture currently relying on the empty-string default would break. Reject for alpha.172, possibly revisit later.

## R2. Bind-time invariant

**File**: `packages/field/src/FieldDefinitionRegistry.php` (lines 39 and 127)

**Decision**: The registry's `registerCoreFields()` and `registerBundleFields()` both compare `$field->getTargetEntityTypeId()` against the registration's `$entityTypeId`; mismatch (including empty-vs-non-empty) throws `\InvalidArgumentException`.

**Exact message format** (line 39, paraphrased):

```
Core field "<name>" declares targetEntityTypeId "<actual>" but is being registered against entity type "<expected>".
```

Bundle-field path uses the same shape with `FieldDefinition` instead of `Core field` in the prefix.

**Rationale**: Catches both *missing* (`''`) and *typo'd* targets at registration time, before the manifest is committed. Deterministic and asserts cleanly in a negative test.

## R3. Concrete defects in framework providers (the original issue + sweep)

| # | File:Line | Field | Owning entity type | Status |
|---|-----------|-------|--------------------|--------|
| 1 | `packages/groups/src/GroupsServiceProvider.php:43` | `description` | `group_type` | DEFECT — missing `targetEntityTypeId` |
| 2 | `packages/taxonomy/src/TaxonomyServiceProvider.php:32` | `description` | `taxonomy_vocabulary` | DEFECT — missing `targetEntityTypeId` |
| 3 | `packages/taxonomy/src/TaxonomyServiceProvider.php:39` | `weight` | `taxonomy_vocabulary` | DEFECT — missing `targetEntityTypeId` *(not mentioned in #1388)* |

Defect #3 was caught by the FR-003 sweep clause. It is a real boot-blocker (same exception class, same kernel-fail outcome) and ships in the alpha.172 fix alongside #1 and #2.

## R4. Clean framework call sites (sweep result)

All other production call sites already pass `targetEntityTypeId` correctly:

- `packages/field/src/FieldDefinitionRegistry.php` — `synthesizeCoreField()` injects from `$entityTypeId` argument.
- `packages/entity/src/Attribute/EntityMetadataReader.php:113` — passes `$entityTypeId ?? ''` (caller responsibility, but not a framework defect because attribute-driven entities flow through a different path).
- `packages/field/src/BundleTemplateCompiler.php:138` — passes `$bundleTpl->entityType`.
- `packages/genealogy/src/GenealogyFieldDefinitions.php` — every line explicitly sets `targetEntityTypeId: $et`.

Test-only construction (no bind) was excluded from the sweep — these instances never reach the registry.

## R5. Canonical construction pattern (chosen for the fix)

**Decision**: Match `GenealogyFieldDefinitions` — set `targetEntityTypeId: '<entity_type_id>'` as a named parameter on the existing constructor call, alongside `name`, `type`, `label`, `description`, `settings`.

**Example diff for the Groups defect**:

```diff
 'description' => new FieldDefinition(
     name: 'description',
     type: 'text',
+    targetEntityTypeId: 'group_type',
     label: 'Description',
     description: 'Human-readable description of this group type.',
     settings: ['weight' => 5],
 ),
```

**Rationale**: Minimum-diff, follows existing pattern, stays inside a single named-parameter call. No helper, no factory, no refactor.

## R6. CHANGELOG location and format

**File**: `/home/jones/dev/waaseyaa/CHANGELOG.md` (repository root).

**Current top entry**: `## [0.1.0-alpha.171] - 2026-05-04`.

**Format observed**:

```markdown
## [0.1.0-alpha.X] - YYYY-MM-DD

### Fixed
- **Bold short title** — explanation. (#issue)

### Added
- ...

### Changed
- ...
```

The alpha.172 entry sits at the top of the file, above alpha.171.

## R7. `docs/specs/entity-system.md` freshness

**Decision (provisional)**: WP03 will read the file and confirm whether the binding-invariant section is still accurate. If it doesn't say "core/bundle fields require `targetEntityTypeId === EntityType::id` at registration time", add a single short paragraph stating that. Then run `tools/drift-detector.sh` to verify no other spec is stale.

**Rationale**: Stale specs are a risk per `CLAUDE.md` ("Stale specs cause bad code"). Worth a one-paragraph audit even if the spec is already clean.

## R8. Open questions

None. All Phase 0 unknowns are resolved.

## References

- GitHub issue [#1388](https://github.com/jonesrussell/waaseyaa/issues/1388)
- `packages/field/src/FieldDefinition.php`
- `packages/field/src/FieldDefinitionRegistry.php`
- `packages/groups/src/GroupsServiceProvider.php`
- `packages/taxonomy/src/TaxonomyServiceProvider.php`
- `packages/genealogy/src/GenealogyFieldDefinitions.php` (canonical pattern)
- `CHANGELOG.md`
