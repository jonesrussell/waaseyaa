# Mission Specification: Alpha.172 FieldDefinition Invariant Fix

**Mission ID**: `01KQTK95TKYCKCFA1C07XE0CFM`
**Mission Slug**: `alpha-172-fielddefinition-invariant-fix-01KQTK95`
**Mission Type**: `software-dev`
**Created**: 2026-05-04
**Target Branch**: `main`
**Tracking Issue**: [waaseyaa#1388](https://github.com/jonesrussell/waaseyaa/issues/1388)
**Triggering Mission (downstream)**: Minoo `upgrade-waaseyaa-alpha-171-01KQTDC2` WP03

---

## Overview

Alpha.171 of the Waaseyaa framework tightened the field-binding invariant so that every `FieldDefinition` must declare its `targetEntityTypeId` before it can be bound to an entity type. Two framework providers — `Waaseyaa\Groups\GroupsServiceProvider` and `Waaseyaa\Taxonomy\TaxonomyServiceProvider` — construct their core `description` `FieldDefinition` without setting that property. As a result, on a clean alpha.171 install, the kernel cannot register `group_type` or `taxonomy_vocabulary`, and downstream consumers (Minoo first, others to follow) cannot boot. Minoo cannot patch this without editing `vendor/`, which is explicitly forbidden.

This mission restores invariant compliance in those two providers, sweeps every other framework-internal `FieldDefinition` construction site for the same defect, locks the invariant with regression tests at both unit and integration levels, and ships alpha.172 with release notes describing the fix.

## User Scenarios & Testing

### Primary actors

- **Framework consumer** (e.g. Minoo, Claudriel): a downstream application that pulls `waaseyaa/*` via Composer and boots a kernel that registers groups and/or taxonomy.
- **Framework maintainer**: the Waaseyaa core team, responsible for invariant correctness and release readiness.

### Acceptance scenarios

#### Scenario 1 — Group type registers cleanly

**Given** a consumer running `waaseyaa/groups` at the alpha.172 build,
**When** the kernel boots and `GroupsServiceProvider::register()` runs,
**Then** the `group_type` entity type registers without throwing, all of its core `FieldDefinition`s (including `description`) carry a non-empty `targetEntityTypeId`, and `EntityTypeManager::getDefinition('group_type')` returns the type.

#### Scenario 2 — Taxonomy vocabulary registers cleanly

**Given** a consumer running `waaseyaa/taxonomy` at the alpha.172 build,
**When** the kernel boots and `TaxonomyServiceProvider::register()` runs,
**Then** the `taxonomy_vocabulary` entity type registers without throwing, all of its core `FieldDefinition`s (including `description`) carry a non-empty `targetEntityTypeId`, and `EntityTypeManager::getDefinition('taxonomy_vocabulary')` returns the type.

#### Scenario 3 — Cross-framework sweep

**Given** every framework-internal call site that constructs a `FieldDefinition` and binds it to an entity type,
**When** the regression test sweep runs against the registered manifest,
**Then** every bound `FieldDefinition` reports a non-empty `targetEntityTypeId` matching the entity type it is bound to.

#### Scenario 4 — Invariant lock-in

**Given** a contributor introduces a new `FieldDefinition` in a framework provider without setting `targetEntityTypeId`,
**When** the test suite runs,
**Then** at least one regression test fails with a message that names the offending entity type and field, so the regression cannot reach a release.

#### Scenario 5 — Downstream Minoo recovery (out-of-scope verification)

**Given** Minoo's WP03 integration suite under alpha.171 (16 failures rooted in this issue),
**When** Minoo upgrades to the alpha.172 build produced by this mission,
**Then** the 16 failures attributable to `group_type` / `taxonomy_vocabulary` registration disappear (Minoo will verify on its own; this mission only ensures the framework side is correct).

### Edge cases

- A provider chain where a child provider extends or overrides a parent provider's field definitions — the child must still surface a `targetEntityTypeId` on every bound field.
- An `EntityType` that is registered with no fields — must remain a no-op; the regression test must not produce false positives for empty bundles.
- A `FieldDefinition` constructed for use *outside* the bind path (e.g. ad-hoc form rendering, schema dumps) — the invariant only applies at bind time, so the test must scope to bound definitions.
- A provider that registers fields lazily inside `boot()` rather than `register()` — the regression test must exercise the post-boot manifest, not just post-register state.

## Requirements

### Functional Requirements

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| FR-001 | The framework MUST set `targetEntityTypeId` on the `description` `FieldDefinition` constructed by `GroupsServiceProvider` for `group_type`. | Must | Open |
| FR-002 | The framework MUST set `targetEntityTypeId` on the `description` `FieldDefinition` constructed by `TaxonomyServiceProvider` for `taxonomy_vocabulary`. | Must | Open |
| FR-003 | The framework MUST audit every `FieldDefinition` constructor call inside `packages/*/src/**` and confirm each bound definition declares `targetEntityTypeId`. Any additional defects discovered MUST be patched in this mission. | Must | Open |
| FR-004 | The framework MUST add a regression test that boots a minimal kernel (in-memory storage acceptable) and asserts `group_type` registers without exception. | Must | Open |
| FR-005 | The framework MUST add a regression test that boots a minimal kernel and asserts `taxonomy_vocabulary` registers without exception. | Must | Open |
| FR-006 | The framework MUST add a manifest-level test that walks every entity type registered via `EntityTypeManager` after kernel boot and asserts every bound `FieldDefinition` has a non-empty `targetEntityTypeId` equal to its bundle's entity type id. | Must | Open |
| FR-007 | The framework MUST publish release notes for alpha.172 that name issue #1388, list the providers patched, and credit the Minoo upgrade mission as the discovery source. | Must | Open |
| FR-008 | The framework SHOULD add a brief migration note (in CHANGELOG / release notes) documenting the alpha.165–alpha.171 reconciliations called out in #1388 (named-param rename `fieldDefinitions:` → `_fieldDefinitions:`, `setKernelResolver()` → `setKernelServices()` + `mergeChildProvider()`, `JsonResponseTrait` redesign) so other consumers can act on them. | Should | Open |

### Non-Functional Requirements

| ID | Requirement | Threshold | Status |
|----|-------------|-----------|--------|
| NFR-001 | The full PHPUnit suite MUST pass on the alpha.172 build. | `vendor/bin/phpunit` exits 0 with zero failures and zero errors. | Open |
| NFR-002 | The new regression tests MUST run in under 5 seconds total on a developer laptop. | Total elapsed for the new tests is below 5000ms when invoked in isolation. | Open |
| NFR-003 | The fix MUST NOT introduce any new package-layer violations. | `bin/check-package-layers` exits 0. | Open |
| NFR-004 | The fix MUST NOT introduce any new Composer policy violations. | `bin/check-composer-policy` exits 0. | Open |
| NFR-005 | The release-notes update MUST link issue #1388, the merging PR, and the alpha.172 tag. | All three references are present in the alpha.172 entry. | Open |

### Constraints

| ID | Constraint | Status |
|----|------------|--------|
| C-001 | The mission MUST NOT edit anything under `vendor/`. | Open |
| C-002 | The mission MUST stay strictly framework-scoped (`packages/*`, `bin/`, `defaults/`, root config, docs). No Minoo, Claudriel, or other consumer code may be touched. | Open |
| C-003 | The fix MUST follow existing architectural patterns — in particular, `FieldDefinition` must be constructed using whatever canonical builder/named-param shape is already used by `name`, `label`, and similar fields for the same providers. | Open |
| C-004 | The mission MUST respect the layer architecture defined in `CLAUDE.md` (Foundation → Core Data → Content Types → Services → API → AI → Interfaces). No upward imports. | Open |
| C-005 | The mission MUST update any `docs/specs/*.md` that becomes stale because of the fix (e.g. entity-system or field specs that describe the binding invariant), and run `tools/drift-detector.sh` to confirm. | Open |

## Success Criteria

- **SC-1**: A clean install of `waaseyaa/groups` and `waaseyaa/taxonomy` at the alpha.172 build can boot a kernel and register `group_type` and `taxonomy_vocabulary` without throwing the binding invariant exception. Verified by automated tests.
- **SC-2**: Running the full test suite on the alpha.172 build produces zero failures and zero errors. Verified by `vendor/bin/phpunit`.
- **SC-3**: The regression tests fail loudly (with offending entity type and field name in the message) if a future contributor introduces a `FieldDefinition` without `targetEntityTypeId`. Verified by mutation-style spot check during code review.
- **SC-4**: Alpha.172 ships with a release-notes entry that names issue #1388, lists the providers patched, and links the merging PR.
- **SC-5**: Minoo's `upgrade-waaseyaa-alpha-171-01KQTDC2` WP03 integration suite (verified independently by Minoo) loses the 16 failures rooted in this issue after upgrading to alpha.172.

## Key Entities

- **FieldDefinition** (`Waaseyaa\Field\FieldDefinition`): the value object describing a single field on an entity type. Carries `name`, `type`, `label`, `targetEntityTypeId`, and other metadata. The alpha.171 binding invariant requires `targetEntityTypeId` to be non-empty before bind.
- **EntityType** (`Waaseyaa\Entity\EntityType`): the descriptor for an entity type. Receives an array of `FieldDefinition`s via the `_fieldDefinitions:` named parameter (per the alpha.171 rename).
- **EntityTypeManager** (`Waaseyaa\Entity\EntityTypeManager`): the registry that holds all entity types. Throws on bind when the invariant is violated.
- **GroupsServiceProvider** (`Waaseyaa\Groups\GroupsServiceProvider`): registers the `group_type` content entity. First defect site.
- **TaxonomyServiceProvider** (`Waaseyaa\Taxonomy\TaxonomyServiceProvider`): registers the `taxonomy_vocabulary` content entity. Second defect site.

## Assumptions

1. The "canonical pattern" referenced in #1388 — i.e. how the `name`/`label` `FieldDefinition`s are constructed in the same providers — is the correct shape to copy for `description`. The implementer will confirm this in WP01 before patching.
2. The binding-invariant exception is thrown with a deterministic message that the regression tests can assert against. If not, WP01 will surface the gap and adjust scope.
3. Cross-cutting sweep across all framework providers will find at most a small number of additional defects (likely zero or one or two). If a much larger sweep is needed, scope will be revisited at the end of WP01 analysis.
4. Release notes live in the standard location for the repo (`CHANGELOG.md` or `docs/releases/*.md` — verified during WP01) and follow the existing format.
5. Minoo's separate WP03 verification is out of scope for this mission; we only deliver a working framework alpha.172.

## Out of Scope

- Editing any consumer code (Minoo, Claudriel, etc.).
- The named-parameter rename `fieldDefinitions:` → `_fieldDefinitions:` (already shipped in alpha.171, not a regression).
- The `setKernelResolver()` → `setKernelServices()` + `mergeChildProvider()` redesign (already shipped in alpha.171).
- The `JsonResponseTrait` redesign (already shipped in alpha.171).
- Any *new* changes to the binding invariant itself (the invariant is correct as shipped; only the call sites are wrong).
- Performance work, schema changes, or new features.

## Dependencies

- Alpha.171 build (current `main`) as the baseline.
- The Composer monorepo wiring (`bin/check-composer-policy`, `bin/check-package-layers`) for gate enforcement.
- `tools/drift-detector.sh` for spec-freshness checks.

## Risks

- **R-1** *(low)*: The binding-invariant exception message may not be stable enough to assert on directly. Mitigation: the manifest-level test (FR-006) does not depend on exception messages.
- **R-2** *(medium)*: The cross-framework sweep may surface additional defects that are non-trivial to fix. Mitigation: WP01 reports findings and scopes follow-up work; the *boot-fail* defects (groups, taxonomy) ship in alpha.172 regardless.
- **R-3** *(low)*: A pre-existing test may be implicitly relying on the buggy behavior. Mitigation: full suite must go green before merge; any failing test is investigated and either fixed or escalated as a separate issue.

## References

- [GitHub issue #1388](https://github.com/jonesrussell/waaseyaa/issues/1388)
- `docs/specs/entity-system.md` (entity binding invariants)
- `packages/groups/src/GroupsServiceProvider.php`
- `packages/taxonomy/src/TaxonomyServiceProvider.php`
- `packages/field/src/FieldDefinition.php`
- `packages/entity/src/EntityTypeManager.php`
- Minoo upgrade mission: `upgrade-waaseyaa-alpha-171-01KQTDC2`, lane commit `2552a87` on `kitty/mission-upgrade-waaseyaa-alpha-171-01KQTDC2-lane-a`.
