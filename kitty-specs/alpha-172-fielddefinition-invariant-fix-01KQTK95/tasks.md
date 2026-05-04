# Tasks: Alpha.172 FieldDefinition Invariant Fix

**Mission ID**: `01KQTK95TKYCKCFA1C07XE0CFM`
**Mission Slug**: `alpha-172-fielddefinition-invariant-fix-01KQTK95`
**Spec**: [spec.md](./spec.md)
**Plan**: [plan.md](./plan.md)
**Branch contract**: current=`main`, planning_base=`main`, merge_target=`main`

---

## Subtask Index

| ID | Description | WP | Parallel |
|----|-------------|----|----------|
| T001 | Add provider unit test for `GroupsServiceProvider` (registers `group_type`, per-field invariant assertions) | WP01 |   | [D] |
| T002 | Add provider unit test for `TaxonomyServiceProvider` (registers `taxonomy_vocabulary`, per-field invariant assertions) | WP01 | [D] |
| T003 | Add registry-level negative test (`FieldDefinitionRegistryInvariantTest`) pinning the `\InvalidArgumentException` contract | WP01 | [D] |
| T004 | Add manifest-level integration test (`FieldDefinitionInvariantTest`) walking every registered entity type and asserting the invariant | WP01 |   | [D] |
| T005 | Run targeted PHPUnit suites; confirm WP01 tests reproduce issue #1388 failures with the documented exception message | WP01 |   | [D] |
| T006 | Patch `packages/groups/src/GroupsServiceProvider.php:43` — set `targetEntityTypeId: 'group_type'` on `description` | WP02 |   |
| T007 | Patch `packages/taxonomy/src/TaxonomyServiceProvider.php:32` — set `targetEntityTypeId: 'taxonomy_vocabulary'` on `description` | WP02 | [P] |
| T008 | Patch `packages/taxonomy/src/TaxonomyServiceProvider.php:39` — set `targetEntityTypeId: 'taxonomy_vocabulary'` on `weight` | WP02 | [P] |
| T009 | Re-run WP01 tests; confirm all four are now green | WP02 |   |
| T010 | Re-run sweep over `packages/*/src/**` for every `new FieldDefinition(...)` call; confirm zero remaining defective bound sites and capture the search command in the WP report | WP03 |   |
| T011 | Audit `docs/specs/entity-system.md` binding-invariant section; patch the field-binding paragraph if it does not state the `targetEntityTypeId === EntityType::id` rule | WP03 |   |
| T012 | Run `tools/drift-detector.sh`; address any flagged specs | WP03 |   |
| T013 | Add `CHANGELOG.md` entry for `[0.1.0-alpha.172]` with `### Fixed` bullet (#1388) and the migration-aid `### Notes` block | WP04 |   |
| T014 | Run all gates: `composer cs-fix`, `composer cs-check`, `composer phpstan`, `vendor/bin/phpunit`, `bin/check-package-layers`, `bin/check-composer-policy` | WP04 |   |
| T015 | Final review pass — confirm PR description references `#1388` and the Minoo discovery mission per `docs/specs/workflow.md` traceability rules | WP04 |   |

Total: 15 subtasks across 4 work packages. Single mechanical lane.

---

## Work Packages

### WP01 — Reproduce + lock invariant

**Prompt file**: [tasks/WP01-reproduce-and-lock-invariant.md](./tasks/WP01-reproduce-and-lock-invariant.md)
**Goal**: Land four failing tests that reproduce issue #1388 and lock the binding invariant against future regressions.
**Priority**: P1 (foundation — every subsequent WP relies on these tests as its acceptance signal).
**Dependencies**: none.
**Estimated prompt size**: ~350 lines.

**Independent test**: From a clean checkout, running the four new tests must fail with `\InvalidArgumentException` and the documented message format. After WP02 lands, the same suite must go green with no other test changes required.

**Included subtasks**:

- [x] T001 Add provider unit test for `GroupsServiceProvider` (WP01)
- [x] T002 Add provider unit test for `TaxonomyServiceProvider` (WP01)
- [x] T003 Add registry-level negative test pinning `\InvalidArgumentException` contract (WP01)
- [x] T004 Add manifest-level integration test walking all entity types (WP01)
- [x] T005 Run targeted suites and document the reproduced failures (WP01)

**Implementation sketch**: write tests against the in-memory kernel boot path used elsewhere in `tests/Integration/`; reuse `DBALDatabase::createSqlite()` if storage is required by the manifest test; assert exact exception message from `FieldDefinitionRegistry`.

**Risks**: tests that boot the kernel may hit unrelated framework wiring — keep the manifest test's provider list to the minimum needed (`waaseyaa/foundation`, `waaseyaa/entity`, `waaseyaa/field`, `waaseyaa/groups`, `waaseyaa/taxonomy`). Avoid pulling the full kernel.

---

### WP02 — Patch the three defect sites

**Prompt file**: [tasks/WP02-patch-providers.md](./tasks/WP02-patch-providers.md)
**Goal**: Set `targetEntityTypeId` on the three defective `FieldDefinition` constructions in `GroupsServiceProvider` and `TaxonomyServiceProvider`. Tests added in WP01 turn green.
**Priority**: P1.
**Dependencies**: WP01 (the tests are the acceptance signal).
**Estimated prompt size**: ~250 lines.

**Independent test**: After this WP lands, the four WP01 tests pass and `vendor/bin/phpunit packages/groups/tests/ packages/taxonomy/tests/ packages/field/tests/Unit/FieldDefinitionRegistryInvariantTest.php tests/Integration/PhaseN/FieldDefinitionInvariantTest.php` exits 0.

**Included subtasks**:

- [ ] T006 Patch `GroupsServiceProvider.php:43` description (WP02)
- [ ] T007 Patch `TaxonomyServiceProvider.php:32` description (WP02)
- [ ] T008 Patch `TaxonomyServiceProvider.php:39` weight (WP02)
- [ ] T009 Re-run WP01 tests; confirm all four green (WP02)

**Implementation sketch**: each patch is a one-line addition of a named-parameter argument inside an existing constructor call. Match the canonical pattern used in `packages/genealogy/src/GenealogyFieldDefinitions.php`.

**Risks**: very low. The diff is mechanical. Do not "improve" surrounding code while in the same edit.

---

### WP03 — Sweep + spec hygiene

**Prompt file**: [tasks/WP03-sweep-and-spec-hygiene.md](./tasks/WP03-sweep-and-spec-hygiene.md)
**Goal**: Confirm no other framework provider has the same defect, and ensure `docs/specs/entity-system.md` reflects the bind-time invariant.
**Priority**: P2.
**Dependencies**: WP02.
**Estimated prompt size**: ~250 lines.

**Independent test**: `tools/drift-detector.sh` exits 0 and the manifest invariant test still passes.

**Included subtasks**:

- [ ] T010 Re-run sweep over `packages/*/src/**` for `new FieldDefinition` (WP03)
- [ ] T011 Audit & patch `docs/specs/entity-system.md` binding-invariant section if stale (WP03)
- [ ] T012 Run `tools/drift-detector.sh` (WP03)

**Implementation sketch**: `rg -n "new FieldDefinition" packages/*/src/` to enumerate call sites; cross-check each against `targetEntityTypeId`; only edit the spec if the existing language fails to capture the post-alpha.171 invariant.

**Risks**: low. The sweep was already exhaustive at planning time; this WP confirms.

---

### WP04 — Release notes + alpha.172 prep

**Prompt file**: [tasks/WP04-release-notes-and-gates.md](./tasks/WP04-release-notes-and-gates.md)
**Goal**: Add the `0.1.0-alpha.172` `CHANGELOG.md` entry, run every gate, and confirm PR-traceability.
**Priority**: P2.
**Dependencies**: WP02, WP03.
**Estimated prompt size**: ~280 lines.

**Independent test**: All gate commands listed in T014 exit 0; `head -30 CHANGELOG.md` shows the new alpha.172 entry as the topmost.

**Included subtasks**:

- [ ] T013 Add `CHANGELOG.md` `[0.1.0-alpha.172]` entry (WP04)
- [ ] T014 Run cs-fix / cs-check / phpstan / phpunit / layer + policy gates (WP04)
- [ ] T015 Final review pass + PR-description traceability (WP04)

**Implementation sketch**: edit `CHANGELOG.md` in place per the format documented in `plan.md` § "Release notes design"; run gates from repo root; do not run `git commit` (the implement loop owns that).

**Risks**: a stray test failure outside the WP01 suite would block this WP. Mitigation: WP02's confirmation of WP01-suite green should already prevent this.

---

## Branch contract (restated for execution)

Current branch at tasks.md generation: `main`. Planning/base: `main`. Merge target: `main`. Each WP's worktree will be allocated by `finalize-tasks` based on the lane partition.

## Parallelization summary

The four WPs are sequential by dependency (WP01 → WP02 → WP03 → WP04). Inside individual WPs, some subtasks are file-independent and could parallelize at the agent level, but per the user's directive this is a single mechanical lane and there is no benefit to parallel execution for a 15-subtask mission.

## MVP scope

WP01 + WP02 alone deliver the issue #1388 fix and lock the invariant. WP03 + WP04 are required for release readiness but do not change runtime behavior. If, for any reason, alpha.172 must ship before WP03/WP04 land, the lane sequence still produces a working framework after WP02.
