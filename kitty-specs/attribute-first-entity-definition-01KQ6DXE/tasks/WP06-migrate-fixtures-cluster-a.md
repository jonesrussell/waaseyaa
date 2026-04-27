---
work_package_id: WP06
title: 'Migrate Test Fixtures (Cluster A: entity, entity-storage, api, mcp, graphql)'
dependencies:
- WP03
requirement_refs:
- FR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T029
- T030
- T031
- T032
- T033
- T034
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "25340"
history:
- date: '2026-04-27'
  note: Initial generation by /spec-kitty.tasks.
authoritative_surface: packages/entity/tests/Unit
execution_mode: code_change
mission_id: 01KQ6DXEQ01S6PVPT6KF5946TA
mission_slug: attribute-first-entity-definition-01KQ6DXE
owned_files:
- packages/entity/tests/Unit/ContentEntityBaseTest.php
- packages/entity/tests/Unit/EntityTypeManagerBundleFieldsTest.php
- packages/entity/tests/Unit/EntityTypeTest.php
- packages/entity/tests/Unit/Validation/EntityTypeValidationConstraintsTest.php
- packages/entity-storage/tests/**
- packages/api/tests/**
- packages/mcp/tests/**
- packages/graphql/tests/**
tags: []
---

# WP06 — Migrate Test Fixtures (Cluster A)

## Branch Strategy

- **Planning base**: `main`. **Merge target**: `main`. Worktree per lane.

## Objective

Mechanical migration of test fixtures and inline `EntityType(fieldDefinitions: …)` calls in the highest-density packages: entity, entity-storage, api, mcp, graphql. Restores green tests in these packages after the WP03 API break.

## Context

For each test file using `fieldDefinitions: …`, decide between two patterns (per `research.md` R5):

**Pattern 1 — Real attribute-decorated test entity** (preferred when the test exercises real entity behavior):
1. Create a small fixture class under `packages/<pkg>/tests/Fixtures/AttributeFirstEntities/<TestName>.php`:
   ```php
   #[ContentEntityType(id: 'test_thing', label: 'Test Thing')]
   final class TestThing extends ContentEntityBase {
       #[Field] public string $title;
       // ... matching the field shape the test needs
   }
   ```
2. In the test, replace `new EntityType(... fieldDefinitions: [...])` with `EntityType::fromClass(TestThing::class)`.

**Pattern 2 — `TestEntityType::stub()`** (use when the test needs raw shape independent of any real class — e.g., schema-controller tests, raw type-shape tests):
1. Replace with `\Waaseyaa\Entity\Tests\Helper\TestEntityType::stub('test_id', [/* FieldDefinition[] */])`.
2. Construct `FieldDefinition` instances inline (the test was already producing field-def data in array form — convert to object form).

Read these:
- WP03 prompt — defines `TestEntityType::stub()` API.
- `kitty-specs/attribute-first-entity-definition-01KQ6DXE/research.md` (R5)

---

## Subtask Guidance

### T029 — Migrate `packages/entity/tests/Unit/` fixtures (3 files)

**Files**:
- `packages/entity/tests/Unit/ContentEntityBaseTest.php`
- `packages/entity/tests/Unit/EntityTypeManagerBundleFieldsTest.php`
- `packages/entity/tests/Unit/EntityTypeTest.php`
- `packages/entity/tests/Unit/Validation/EntityTypeValidationConstraintsTest.php`

**Steps**:
1. For each test file, identify the test's intent:
   - `EntityTypeTest.php`: tests `EntityType` shape. Use Pattern 2 (`TestEntityType::stub()`).
   - `ContentEntityBaseTest.php`: tests entity behavior. Use Pattern 1 (real fixture class).
   - `EntityTypeManagerBundleFieldsTest.php`: bundle-fields registration. Use Pattern 1 with a multi-bundle fixture.
   - `EntityTypeValidationConstraintsTest.php`: tests validation constraint compilation. Use Pattern 2.
2. For each fixture class created, place under `packages/entity/tests/Fixtures/AttributeFirstEntities/`.
3. Update each test file's setup methods.

**Validation**:
- [ ] `vendor/bin/phpunit packages/entity/tests/` green.
- [ ] No `fieldDefinitions:` parameter passed to any `new EntityType(...)` call site.

---

### T030 — Migrate `packages/entity-storage/tests/Unit/` fixtures (4 files)

**Files**:
- `packages/entity-storage/tests/Unit/CastPersistenceIntegrationTest.php`
- `packages/entity-storage/tests/Unit/EntityRepositoryTest.php`
- `packages/entity-storage/tests/Unit/SqlEntityStorageBundleQueryRoutingTest.php`
- `packages/entity-storage/tests/Unit/SqlEntityStorageTest.php`

**Steps**: Same approach. These are storage-layer tests; they likely benefit from Pattern 1 (real entity classes) for the round-trip nature. Place fixtures under `packages/entity-storage/tests/Fixtures/AttributeFirstEntities/`.

**Validation**:
- [ ] `vendor/bin/phpunit packages/entity-storage/tests/` green.

---

### T031 — Migrate `packages/api/tests/` fixtures (3 files)

**Files**:
- `packages/api/tests/Fixtures/TranslatableTestEntity.php` (this is already a fixture; possibly migrate to attribute form in place)
- `packages/api/tests/Unit/Controller/SchemaControllerTest.php`
- `packages/api/tests/Unit/ResourceSerializerCastAwareTest.php`
- `packages/api/tests/Unit/ResourceSerializerTest.php`

**Steps**:
1. `TranslatableTestEntity.php` is the starting point — make it a proper attribute-first test entity (`#[ContentEntityType]`, `#[Field]` properties with `translatable: true`).
2. Update the three test files to use the migrated fixture.
3. SchemaController test may use Pattern 2 to test edge cases.

**Validation**:
- [ ] `vendor/bin/phpunit packages/api/tests/` green.

---

### T032 — Migrate `packages/mcp/tests/Unit/` fixtures (5 files)

**Files**: All five `McpController*Test.php` files.

**Steps**: MCP controllers test discovery, manifest, traversal, etc. — they likely use synthetic entity types extensively. Pattern 2 (`TestEntityType::stub`) probably fits best for most. If a few tests exercise real entity behavior, use Pattern 1.

**Validation**:
- [ ] `vendor/bin/phpunit packages/mcp/tests/` green.

---

### T033 — Migrate `packages/graphql/tests/Unit/` fixtures (4 files)

**Files**:
- `packages/graphql/tests/Unit/GraphQlEndpointTest.php`
- `packages/graphql/tests/Unit/Resolver/EntityResolverTest.php`
- `packages/graphql/tests/Unit/Schema/SchemaValidationTest.php`
- `packages/graphql/tests/Unit/SchemaFactoryTest.php`

**Steps**: GraphQL tests typically need entity types with explicit field types (the schema generator is type-driven). Pattern 1 with attribute-decorated fixtures is the right fit.

**Validation**:
- [ ] `vendor/bin/phpunit packages/graphql/tests/` green.

---

### T034 — Run package phpunit; verify green

**Purpose**: Aggregate verification gate.

**Steps**:
1. From repo root: `vendor/bin/phpunit packages/entity/tests/ packages/entity-storage/tests/ packages/api/tests/ packages/mcp/tests/ packages/graphql/tests/`.
2. Confirm green. If any failures remain, investigate before declaring WP06 done.

**Validation**:
- [ ] All five packages' tests pass.
- [ ] No new test files use `fieldDefinitions:` parameter on `EntityType` constructor calls.

---

## Definition of Done

- All 6 subtasks ticked.
- All five packages' tests are green.
- No file outside `owned_files` modified.

## Risks

- **Cross-package fixture sharing**: some tests may import fixtures from other packages. Track imports carefully when migrating fixtures.
- **`TestEntityType::stub` requires `FieldDefinition` instances**: tests that previously passed raw arrays must construct `FieldDefinition` objects. Look up the constructor.
- **Bundle-related tests**: `EntityTypeManagerBundleFieldsTest` may need careful migration since bundle-scoped attributes are out of M1. Use `EntityTypeManager::addBundleFields()` in fixtures where multi-bundle behavior is needed (the imperative path is unchanged in M1).

## Reviewer guidance

- Verify the test intent is preserved — same field shapes, same assertions, just routed through the new API.
- Verify Pattern 1 fixtures are in the canonical fixtures directory under each package.
- Confirm no test reaches outside `owned_files`.

## Implementation command

```
spec-kitty agent action implement WP06 --agent <name>
```

## Activity Log

- 2026-04-27T04:52:23Z – claude:opus-4-7:implementer:implementer – shell_pid=19184 – Started implementation via action command
- 2026-04-27T05:17:43Z – claude:opus-4-7:implementer:implementer – shell_pid=19184 – Ready for review: 1208/1208 green; new attribute-first fixtures across entity/entity-storage/graphql; no new EntityType(... fieldDefinitions:) call sites.
- 2026-04-27T05:18:45Z – claude:opus-4-7:reviewer:reviewer – shell_pid=25340 – Started review via action command
- 2026-04-27T05:21:23Z – claude:opus-4-7:reviewer:reviewer – shell_pid=25340 – Review passed: 1208 tests green; diff fully within owned globs; sole residual fieldDefinitions: in EntityTypeFromClassTest:144 is the deliberate API-break test; PHPStan errors decreased by 42 vs parent (no new violations); 4 flagged deviations (FieldStorage gap bridge, RequiredLabelFixture id rename, ContentEntityBase passthrough, TranslatableTestEntity positional clone) are all acceptable per spec C-006.
