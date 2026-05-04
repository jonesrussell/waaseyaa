# Implementation Plan: Alpha.172 FieldDefinition Invariant Fix

**Mission ID**: `01KQTK95TKYCKCFA1C07XE0CFM`
**Mission Slug**: `alpha-172-fielddefinition-invariant-fix-01KQTK95`
**Spec**: [spec.md](./spec.md)
**Branch contract**: current=`main`, planning_base=`main`, merge_target=`main`
**Created**: 2026-05-04

---

## Summary

Alpha.171 hardened `Waaseyaa\Field\FieldDefinitionRegistry` to assert that every bound `FieldDefinition` declares `targetEntityTypeId === $entityTypeId` at registration time. Three concrete provider call sites violate the assertion today, so a clean alpha.171 boot of `waaseyaa/groups` or `waaseyaa/taxonomy` throws `\InvalidArgumentException` and the kernel fails. The fix is small, mechanical, and follows the same named-parameter shape already used elsewhere (e.g. `GenealogyFieldDefinitions`, `BundleTemplateCompiler::synthesize…`). The mission ships alpha.172 with the patches, regression tests at unit + manifest level, and CHANGELOG notes.

## Technical Context

| Aspect | Value |
|--------|-------|
| Language | PHP 8.4+ (`declare(strict_types=1)` in every file) |
| Test framework | PHPUnit 10.5 (no `-v` flag — rejected by 10.5) |
| Static analysis | PHPStan 2.x, level 5 |
| Code style | PHP-CS-Fixer (`composer cs-check` / `cs-fix`) |
| Build & layer gates | `bin/check-package-layers`, `bin/check-composer-policy` |
| Branch & merge | local mission worktree → PR to `main` (single-lane) |
| Affected packages | `waaseyaa/groups` (L2), `waaseyaa/taxonomy` (L2), `waaseyaa/field` (L1, tests only), root (CHANGELOG) |
| Storage | N/A — no schema changes |
| Performance | N/A — no hot-path changes; new tests must finish under 5s total |

## Charter Check

*GATE: must pass before Phase 0 research. Re-checked after Phase 1 design.*

Charter context returned `mode: compact` with template set `software-dev-default`, paradigm `domain-driven-design`, directives `DIR-001`, `DIR-002`, `DIR-003`. No conflicts:

- **Quality Gates** — full PHPUnit suite must remain green; this mission adds tests, never skips.
- **Testing Standards** — regression tests included for both reproduction and invariant lock-in.
- **Branch Strategy** — `main` is the merge target (per `setup-plan` JSON).
- **Project Directives** — no exception requests required.

Re-evaluation after Phase 1: still clean. No directive carve-outs needed.

## Project Structure

### Documentation (this feature)

```
kitty-specs/alpha-172-fielddefinition-invariant-fix-01KQTK95/
├── spec.md
├── plan.md              ← this file
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output (intentionally short — no schema change)
├── quickstart.md        ← Phase 1 output (verification recipe)
├── checklists/requirements.md
├── meta.json
└── tasks.md             ← created by /spec-kitty.tasks (NOT here)
```

### Source code (repository root)

Single PHP monorepo. Files touched by this mission:

```
packages/
├── groups/
│   ├── src/GroupsServiceProvider.php           # PATCH: line 43, add targetEntityTypeId: 'group_type'
│   └── tests/Unit/GroupsServiceProviderTest.php  # NEW or extend: registration + per-field invariant
├── taxonomy/
│   ├── src/TaxonomyServiceProvider.php         # PATCH: line 32 (description) + line 39 (weight)
│   └── tests/Unit/TaxonomyServiceProviderTest.php  # NEW or extend
└── field/
    └── tests/Unit/FieldDefinitionRegistryInvariantTest.php  # NEW: pin exception contract

tests/
└── Integration/PhaseN/FieldDefinitionInvariantTest.php  # NEW: manifest-wide sweep

CHANGELOG.md                                     # NEW entry for [0.1.0-alpha.172]
docs/specs/entity-system.md                      # CONFIRM (touch only if section is stale)
```

**Structure Decision**: existing PHP monorepo layout is correct; no new directories required.

## Phase 0 — Research (consolidated)

See `research.md`. Findings (summary):

1. **`FieldDefinition` constructor (`packages/field/src/FieldDefinition.php:15`)** uses named parameters; `targetEntityTypeId: string = ''` is optional with an empty-string default. Empty string passes construction but fails registration.
2. **Bind-time invariant (`packages/field/src/FieldDefinitionRegistry.php:39, :127`)** throws `\InvalidArgumentException` with a deterministic message:
   `Core field "<name>" declares targetEntityTypeId "<actual>" but is being registered against entity type "<expected>".`
3. **Three concrete defects:**
   - `packages/groups/src/GroupsServiceProvider.php:43` — `description` for `group_type`.
   - `packages/taxonomy/src/TaxonomyServiceProvider.php:32` — `description` for `taxonomy_vocabulary`.
   - `packages/taxonomy/src/TaxonomyServiceProvider.php:39` — `weight` for `taxonomy_vocabulary`. *(This is in addition to `description` — not surfaced in #1388 but caught by the FR-003 sweep.)*
4. **All other framework production call sites are clean** — `FieldDefinitionRegistry::synthesizeCoreField()`, `EntityMetadataReader.php:113`, `BundleTemplateCompiler.php:138`, every line in `GenealogyFieldDefinitions` already pass the entity type id explicitly.
5. **Test-only sites** in `packages/entity/tests/`, `packages/graphql/tests/`, etc. construct `FieldDefinition` without `targetEntityTypeId` for constraint-validation paths that never bind. Out of scope.
6. **CHANGELOG** lives at `/home/jones/dev/waaseyaa/CHANGELOG.md`. Current top entry is `0.1.0-alpha.171` (2026-05-04). Format: `## [version] - DATE` with `### Fixed` / `### Added` / `### Changed` blocks containing bold-prefix bullets and `(#issue)` suffixes.
7. **Spec freshness** — `docs/specs/entity-system.md` exists; the binding-invariant section may need a one-paragraph clarification noting that core/bundle fields require `targetEntityTypeId` matching their owning entity type. WP03 confirms current state and patches if needed.

## Phase 1 — Design

### Data model

No schema or storage changes. The fix is purely PHP construction-site corrections plus tests. See `data-model.md` for the (intentionally short) data-model note.

### Contracts

No new HTTP / GraphQL / CLI contracts. The behavior change is invisible to API consumers — the kernel goes from "throws on register" to "registers cleanly". The `FieldDefinitionRegistry` exception contract is unchanged. No `contracts/` directory generated.

### Code-shape decision: canonical FieldDefinition construction for providers

**Decision**: Use named parameters that include `targetEntityTypeId: '<entity_type_id>'` matching the declaring `EntityType::id`. This is the shape already used by `GenealogyFieldDefinitions` and `BundleTemplateCompiler::synthesizeCoreField()`, and is the minimum-diff fix for the broken sites.

**Rationale**: `FieldDefinitionRegistry` already enforces `targetEntityTypeId === $entityTypeId`. Setting the field to the matching string is the smallest, most local correction. No builder, factory, or default-injection helper is needed; doing so would expand scope and risk a follow-on refactor for what is fundamentally a three-line patch.

**Alternatives considered**:
- *Default `targetEntityTypeId` from `EntityType` at registration time*: would silently mask the same bug class instead of catching it. Rejected.
- *Make `targetEntityTypeId` constructor-required (no default)*: a stronger invariant, but a much larger blast radius — every test fixture currently relying on the empty-string default would break. Possible follow-up; out of scope for alpha.172.

### Test design

Three layers, all PHPUnit:

1. **Provider unit tests (`packages/groups/tests/Unit/GroupsServiceProviderTest.php`, `packages/taxonomy/tests/Unit/TaxonomyServiceProviderTest.php`)**:
   For each provider, drive `register()` against a minimal kernel context, assert the entity type is registered in `EntityTypeManager`, and assert each core field's `getTargetEntityTypeId()` equals the owning entity type id. These tests fail today (issue #1388 reproduction); they pass after the patch.

2. **Manifest-level invariant sweep (`tests/Integration/PhaseN/FieldDefinitionInvariantTest.php`)**:
   Boot a minimal kernel with all framework providers registered. Walk every entity type in `EntityTypeManager`, walk every core/bundle `FieldDefinition`, assert `getTargetEntityTypeId()` is non-empty AND equals the owning entity type id. Failure message names the offending entity type and field. This is the regression lock — any future provider that ships a defect will fail this test.

3. **Registry-level negative test (`packages/field/tests/Unit/FieldDefinitionRegistryInvariantTest.php`)**:
   Construct a `FieldDefinition` with empty `targetEntityTypeId`, attempt to register it against a non-empty `$entityTypeId`, assert `\InvalidArgumentException` is thrown with the documented message format. Locks the exception contract that the manifest test relies on.

### Release notes design

Add a new top entry to `CHANGELOG.md`:

```markdown
## [0.1.0-alpha.172] - 2026-05-DD

### Fixed

- **groups, taxonomy: core `description` (and taxonomy `weight`) FieldDefinition now declares `targetEntityTypeId`** — `GroupsServiceProvider` and `TaxonomyServiceProvider` constructed core FieldDefinitions without `targetEntityTypeId`, which the alpha.171 binding invariant correctly rejected. Kernel boot now registers `group_type` and `taxonomy_vocabulary` cleanly. Discovered while upgrading Minoo to alpha.171 (mission `upgrade-waaseyaa-alpha-171-01KQTDC2` WP03). (#1388)

### Notes

- For consumers upgrading from alpha.165 → alpha.171 → alpha.172, three additional reconciliations may be required (already shipped in alpha.171; restated for posterity):
  - `EntityType` constructor named param renamed: `fieldDefinitions:` → `_fieldDefinitions:`.
  - `ServiceProvider::setKernelResolver()` removed; replaced by `setKernelServices(KernelServicesInterface)` plus `mergeChildProvider()`.
  - `Waaseyaa\Api\JsonResponseTrait` redesigned (single `jsonApiResponse()` returning `application/vnd.api+json`); previous `json()`/`jsonBody()` surface dropped.
```

The "Notes" stanza addresses spec FR-008 (consumer migration aid).

### Spec freshness

If `docs/specs/entity-system.md` does not already document the bind-time invariant `FieldDefinition::targetEntityTypeId === EntityType::id`, add a paragraph in the field-binding section. WP03 confirms current state; patches if needed.

## Phase 2 — Build sequencing (preview only — work packages live in `tasks.md`)

The fix is a single mechanical lane. Suggested WP shape for `/spec-kitty.tasks`:

| WP | Title | Scope |
|----|-------|-------|
| WP01 | Reproduce + lock invariant | Add failing tests (T1, T2, T3 above), confirm they reproduce issue #1388 |
| WP02 | Patch GroupsServiceProvider + TaxonomyServiceProvider | Set `targetEntityTypeId` on the three defect sites; tests from WP01 turn green |
| WP03 | Sweep + spec hygiene | Re-run sweep, confirm no other framework call site is missing `targetEntityTypeId`, update `docs/specs/entity-system.md` if needed, run `tools/drift-detector.sh` |
| WP04 | Release notes + alpha.172 prep | Add `CHANGELOG.md` entry, run `composer cs-fix && composer phpstan && vendor/bin/phpunit && bin/check-composer-policy && bin/check-package-layers` |

Final WP partitioning is the responsibility of `/spec-kitty.tasks`; this is preview-only.

## Gates (must pass before merge)

- [ ] `vendor/bin/phpunit` — 0 failures, 0 errors
- [ ] `composer phpstan` — clean at level 5
- [ ] `composer cs-check` — clean
- [ ] `bin/check-package-layers` — clean
- [ ] `bin/check-composer-policy` — clean
- [ ] `tools/drift-detector.sh` — no stale specs after the change
- [ ] PR description references `#1388` and the Minoo discovery mission

## Risks revisited

- **R-1** (binding-invariant message stability): mitigated by the registry-level negative test that pins the exception message contract.
- **R-2** (sweep finds more defects): one extra defect already found (`TaxonomyServiceProvider.php:39` weight). Full sweep ran during planning; no additional defects in production code.
- **R-3** (pre-existing test relying on bug): if any test breaks, treat as a real bug and fix in the same WP. PR description must enumerate any such test.

## Complexity Tracking

No charter violations to justify. Section intentionally empty.

## Branch contract (restated)

Current branch at plan completion: `main`. Planning/base branch: `main`. Final merge target: `main`. `branch_matches_target = true`.
