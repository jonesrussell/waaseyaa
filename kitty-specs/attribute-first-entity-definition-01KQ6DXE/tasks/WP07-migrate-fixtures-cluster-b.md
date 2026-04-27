---
work_package_id: WP07
title: 'Migrate Test Fixtures (Cluster B: genealogy, ssr, testing, ai-vector, admin-surface, cli)'
dependencies:
- WP03
requirement_refs:
- FR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T035
- T036
- T037
- T038
- T039
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "20088"
history:
- date: '2026-04-27'
  note: Initial generation by /spec-kitty.tasks.
authoritative_surface: packages/genealogy/tests
execution_mode: code_change
mission_id: 01KQ6DXEQ01S6PVPT6KF5946TA
mission_slug: attribute-first-entity-definition-01KQ6DXE
owned_files:
- packages/genealogy/tests/**
- packages/ssr/tests/**
- packages/testing/tests/**
- packages/ai-vector/tests/**
- packages/admin-surface/tests/**
- packages/cli/tests/**
tags: []
---

# WP07 — Migrate Test Fixtures (Cluster B)

## Branch Strategy

- **Planning base**: `main`. **Merge target**: `main`. Worktree per lane.

## Objective

Migrate the remaining test fixtures across genealogy, ssr, testing, ai-vector, admin-surface, and cli packages to attribute-first form. Restores green tests in these packages after the WP03 API break. Use the same Pattern 1 / Pattern 2 decision logic as WP06.

## Context

Read WP06 prompt for the migration patterns and `TestEntityType::stub()` API. The same recipes apply here.

---

## Subtask Guidance

### T035 — Migrate `packages/genealogy/tests/Unit/` fixtures (2 files)

**Files**:
- `packages/genealogy/tests/Unit/GenealogyFamilyServiceTest.php`
- `packages/genealogy/tests/Unit/GenealogyPedigreeServiceTest.php`

**Steps**:
1. These are service-level tests, not entity-shape tests. Pattern 1 (real fixture entities or — better — use the now-attribute-first production entities migrated in WP04).
2. If WP04 has merged, just use `GenealogyPerson::class`, `GenealogyFamily::class`, etc. directly via `EntityType::fromClass()` in the test setup.
3. If WP04 hasn't merged when WP07 starts (parallel execution), create local test fixtures and migrate to production classes once WP04 lands. Coordinate via dependency declaration.

**Validation**:
- [ ] `vendor/bin/phpunit packages/genealogy/tests/` green.

---

### T036 — Migrate `packages/ssr/tests/Unit/` fixtures (2 files)

**Files**:
- `packages/ssr/tests/Unit/EntityRendererTest.php`
- `packages/ssr/tests/Unit/RenderControllerTest.php`

**Steps**: SSR tests render entity content. Pattern 1 (real fixtures) for content entities; Pattern 2 for synthetic shape tests.

**Validation**:
- [ ] `vendor/bin/phpunit packages/ssr/tests/` green.

---

### T037 — Migrate `packages/testing/tests/Unit/` fixtures (2 files)

**Files**:
- `packages/testing/tests/Unit/EntityFactoryTest.php`
- `packages/testing/tests/Unit/EntityTypeFixtureValuesTest.php`

**Steps**:
1. `packages/testing/` is a test-helper package — its `EntityFactory` and `EntityTypeFixtureValues` are consumed by other packages' tests. Migrate carefully so the public API of these helpers continues to work.
2. The helpers may need extension to consume attribute-first entity types more ergonomically (e.g., a `forClass(string $class)` factory mode). Keep that minimal — if extension is non-trivial, scope to test-internal usage in this WP and file a follow-up.

**Validation**:
- [ ] `vendor/bin/phpunit packages/testing/tests/` green.
- [ ] No regressions in consuming packages (run WP06's package tests too as a smoke check).

**Risks**: `packages/testing/`'s helpers are widely consumed. If their public API changes, downstream packages may break. Audit consumers before changing public signatures.

---

### T038 — Migrate remaining test fixtures (ai-vector, admin-surface, cli — 3 files)

**Files**:
- `packages/ai-vector/tests/Unit/SearchControllerTest.php`
- `packages/admin-surface/tests/Unit/Host/GenericAdminSurfaceHostTest.php`
- `packages/cli/tests/Unit/Command/Make/MakeProviderCommandTest.php`

**Steps**:
1. `SearchControllerTest`: vector-search tests. Pattern 2 (`TestEntityType::stub`) for synthetic types.
2. `GenericAdminSurfaceHostTest`: admin surface tests. Pattern 1 (real fixtures) typically.
3. `MakeProviderCommandTest`: tests the CLI generator. Now that WP05 updated the generator template, this test's expectations may need updating to match the new output. Confirm the expected scaffold-output text matches the new attribute-first template.

**Validation**:
- [ ] `vendor/bin/phpunit packages/ai-vector/ packages/admin-surface/ packages/cli/` green.

---

### T039 — Run package phpunit; verify green

**Purpose**: Aggregate verification gate for WP07's six packages.

**Steps**:
1. From repo root: `vendor/bin/phpunit packages/genealogy/tests/ packages/ssr/tests/ packages/testing/tests/ packages/ai-vector/tests/ packages/admin-surface/tests/ packages/cli/tests/`.
2. Confirm green.

**Validation**:
- [ ] All six packages' tests pass.
- [ ] No `fieldDefinitions:` parameter remains in the migrated test files.

---

## Definition of Done

- All 5 subtasks ticked.
- All six packages' tests are green.
- No file outside `owned_files` modified.

## Risks

- **`packages/testing/` helpers are public API**: be careful with signature changes; downstream tests in WP06's packages depend on them.
- **CLI generator test (`MakeProviderCommandTest`) tightly couples to WP05**: ensure WP05 has merged before finalizing WP07.

## Reviewer guidance

- Verify migrations are mechanical: same intent, new API.
- Verify `packages/testing/` helpers still work for WP06's consumers (run those tests too).

## Implementation command

```
spec-kitty agent action implement WP07 --agent <name>
```

## Activity Log

- 2026-04-27T05:22:09Z – claude:opus-4-7:implementer:implementer – shell_pid=28680 – Started implementation via action command
- 2026-04-27T05:37:28Z – claude:opus-4-7:implementer:implementer – shell_pid=28680 – Ready for review: 8 test files migrated across 6 packages; all tests green; WP06 smoke check (1208 tests) passes.
- 2026-04-27T05:38:36Z – claude:opus-4-7:reviewer:reviewer – shell_pid=20088 – Started review via action command
- 2026-04-27T05:42:14Z – claude:opus-4-7:reviewer:reviewer – shell_pid=20088 – Review passed: 87 migrated tests green, WP06 smoke 1208/1208, PHPStan improved 250->225 errors, only allowed fieldDefinitions: residuals (TestEntityType::stub) remain
